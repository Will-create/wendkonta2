<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DeliveryProgress
{
    public const NOT_DELIVERED = 'not_delivered';
    public const PARTIAL = 'partial';
    public const FULL = 'full';
    public const IN_PROGRESS = 'in_progress';

    public static function soldQty(object $line): float
    {
        $sold = (float) $line->qty - (float) ($line->return_qty ?? 0);

        return $sold < 0 ? 0.0 : $sold;
    }

    /**
     * @param  array<int>|iterable<int>  $saleIds
     * @return array<int, array{status:string,sold:float,delivered:float,remaining:float}>
     */
    public static function forSales(iterable $saleIds): array
    {
        $saleIds = array_values(array_unique(array_map('intval', is_array($saleIds) ? $saleIds : iterator_to_array($saleIds))));
        if (!$saleIds) {
            return [];
        }

        $lines = DB::table('product_sales')
            ->whereIn('sale_id', $saleIds)
            ->get(['id', 'sale_id', 'qty', 'return_qty']);

        $delivered = DB::table('delivery_products')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_products.delivery_id')
            ->whereIn('deliveries.sale_id', $saleIds)
            ->groupBy('delivery_products.product_sale_id')
            ->select('delivery_products.product_sale_id', DB::raw('SUM(delivery_products.qty) as qty'))
            ->pluck('qty', 'product_sale_id');

        $statuses = DB::table('deliveries')
            ->whereIn('sale_id', $saleIds)
            ->get(['sale_id', 'status']);

        $open = [];
        $legacyDelivered = [];
        foreach ($statuses as $row) {
            $status = (string) $row->status;
            if ($status === '1' || $status === '2') {
                $open[$row->sale_id] = true;
            }
            if ($status === '3') {
                $legacyDelivered[$row->sale_id] = true;
            }
        }

        $tracked = DB::table('delivery_products')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_products.delivery_id')
            ->whereIn('deliveries.sale_id', $saleIds)
            ->pluck('deliveries.sale_id')
            ->flip();

        $bySale = [];
        foreach ($lines as $line) {
            $bySale[$line->sale_id][] = $line;
        }

        $result = [];
        foreach ($saleIds as $saleId) {
            $sold = 0.0;
            $done = 0.0;
            foreach ($bySale[$saleId] ?? [] as $line) {
                $lineSold = self::soldQty($line);
                $sold += $lineSold;
                $done += min($lineSold, (float) ($delivered[$line->id] ?? 0));
            }
            $remaining = max(0, $sold - $done);
            $hasLines = isset($tracked[$saleId]);

            if ($sold <= 0.00001) {
                $status = self::NOT_DELIVERED;
            } elseif ($hasLines && $remaining <= 0.00001) {
                $status = self::FULL;
            } elseif (!$hasLines && isset($legacyDelivered[$saleId]) && !isset($open[$saleId])) {
                $status = self::FULL;
            } elseif (isset($open[$saleId])) {
                $status = self::IN_PROGRESS;
            } elseif ($done > 0.00001) {
                $status = self::PARTIAL;
            } else {
                $status = self::NOT_DELIVERED;
            }

            $result[$saleId] = [
                'status' => $status,
                'sold' => $sold,
                'delivered' => $done,
                'remaining' => $remaining,
            ];
        }

        return $result;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::FULL => __('db.Fully delivered'),
            self::PARTIAL => __('db.Partially delivered'),
            self::IN_PROGRESS => __('db.In progress'),
            default => __('db.Not yet delivered'),
        };
    }

    public static function badge(array $progress): string
    {
        $class = match ($progress['status']) {
            self::FULL => 'badge-success',
            self::PARTIAL => 'badge-warning',
            self::IN_PROGRESS => 'badge-info',
            default => 'badge-secondary',
        };
        $qty = e(self::formatQty($progress['delivered']).' / '.self::formatQty($progress['sold']));

        return '<div class="badge '.$class.'">'.e(self::label($progress['status'])).'</div><div><small>'.$qty.'</small></div>';
    }

    /**
     * @return array<int, array{product_sale_id:int,product_id:int,name:string,code:string,sold:float,delivered:float,remaining:float}>
     */
    public static function linesForSale(int $saleId, ?int $exceptDeliveryId = null): array
    {
        $rows = DB::table('product_sales')
            ->leftJoin('products', 'products.id', '=', 'product_sales.product_id')
            ->where('product_sales.sale_id', $saleId)
            ->orderBy('product_sales.id')
            ->get([
                'product_sales.id',
                'product_sales.product_id',
                'product_sales.qty',
                'product_sales.return_qty',
                'products.name',
                'products.code',
            ]);

        $query = DB::table('delivery_products')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_products.delivery_id')
            ->where('deliveries.sale_id', $saleId);
        if ($exceptDeliveryId) {
            $query->where('delivery_products.delivery_id', '!=', $exceptDeliveryId);
        }
        $delivered = $query
            ->groupBy('delivery_products.product_sale_id')
            ->select('delivery_products.product_sale_id', DB::raw('SUM(delivery_products.qty) as qty'))
            ->pluck('qty', 'product_sale_id');

        $lines = [];
        foreach ($rows as $row) {
            $sold = self::soldQty($row);
            $done = min($sold, (float) ($delivered[$row->id] ?? 0));
            $lines[] = [
                'product_sale_id' => (int) $row->id,
                'product_id' => (int) $row->product_id,
                'name' => $row->name ?: __('db.Removed product #:id', ['id' => $row->product_id]),
                'code' => (string) ($row->code ?? ''),
                'sold' => $sold,
                'delivered' => $done,
                'remaining' => max(0, $sold - $done),
            ];
        }

        return $lines;
    }

    public static function history(int $saleId): array
    {
        $deliveries = DB::table('deliveries')->where('sale_id', $saleId)->orderBy('id')->get();
        $deliveryIds = $deliveries->pluck('id')->all();
        $products = $deliveryIds
            ? DB::table('delivery_products')
                ->leftJoin('products', 'products.id', '=', 'delivery_products.product_id')
                ->whereIn('delivery_products.delivery_id', $deliveryIds)
                ->orderBy('delivery_products.id')
                ->get([
                    'delivery_products.delivery_id',
                    'delivery_products.product_sale_id',
                    'delivery_products.product_id',
                    'delivery_products.qty',
                    'products.name',
                    'products.code',
                ])
            : collect();

        $byDelivery = [];
        foreach ($products as $product) {
            $byDelivery[$product->delivery_id][] = $product;
        }

        $remainingById = [];
        foreach (self::linesForSale($saleId) as $line) {
            $remainingById[$line['product_sale_id']] = $line['remaining'];
        }

        $history = [];
        foreach ($deliveries as $delivery) {
            $items = [];
            foreach ($byDelivery[$delivery->id] ?? [] as $item) {
                $items[] = [
                    'product_sale_id' => (int) $item->product_sale_id,
                    'name' => $item->name ?: __('db.Removed product #:id', ['id' => $item->product_id]),
                    'code' => (string) ($item->code ?? ''),
                    'qty' => (float) $item->qty,
                    'remaining' => $remainingById[$item->product_sale_id] ?? null,
                ];
            }
            $status = (string) $delivery->status;
            $history[] = [
                'id' => (int) $delivery->id,
                'reference_no' => $delivery->reference_no,
                'date' => date(config('date_format') ?: 'd-m-Y', strtotime($delivery->created_at)),
                'status' => $status,
                'status_label' => self::headerStatusLabel($status),
                'note' => $delivery->note,
                'delivered_by' => $delivery->delivered_by,
                'recieved_by' => $delivery->recieved_by,
                'items' => $items,
            ];
        }

        return $history;
    }

    public static function headerStatusLabel(string $status): string
    {
        if ($status === '1') {
            return __('db.Packing');
        }
        if ($status === '2') {
            return __('db.Delivering');
        }

        return __('db.Delivered');
    }

    public static function syncFlags(int $saleId): void
    {
        foreach (self::linesForSale($saleId) as $line) {
            DB::table('product_sales')->where('id', $line['product_sale_id'])->update([
                'is_delivered' => ($line['sold'] > 0 && $line['remaining'] <= 0.00001) ? 1 : 0,
            ]);
        }
    }

    public static function formatQty(float $value): string
    {
        $rounded = round($value, 4);
        if (abs($rounded - round($rounded)) < 0.00001) {
            return (string) (int) round($rounded);
        }

        return rtrim(rtrim(number_format($rounded, 4, '.', ''), '0'), '.');
    }

    /**
     * @param  array<int|string, mixed>  $quantities
     * @return array<int, array{product_sale_id:int,product_id:int,qty:float}>
     */
    public static function assertLines(int $saleId, array $quantities, ?int $exceptDeliveryId = null): array
    {
        $byId = [];
        foreach (self::linesForSale($saleId, $exceptDeliveryId) as $line) {
            $byId[$line['product_sale_id']] = $line;
        }

        $accepted = [];
        $seen = [];
        foreach (self::quantityRows($quantities) as $row) {
            $id = $row['product_sale_id'];
            $qty = $row['qty'];
            if (isset($seen[$id])) {
                throw new \InvalidArgumentException(__('db.Each product can be included only once on the same delivery.'));
            }
            $seen[$id] = true;
            if ($qty === null || $qty === '') {
                continue;
            }
            if (!is_numeric($qty)) {
                throw new \InvalidArgumentException(__('db.Delivery quantity must be a number.'));
            }
            $qty = (float) $qty;
            if ($qty <= 0) {
                continue;
            }
            if (!isset($byId[$id])) {
                throw new \InvalidArgumentException(__('db.A selected product does not belong to this sale.'));
            }
            $remaining = $byId[$id]['remaining'];
            if ($remaining <= 0.00001) {
                throw new \InvalidArgumentException(__('db.:name is already fully delivered.', ['name' => $byId[$id]['name']]));
            }
            if ($qty - $remaining > 0.00001) {
                throw new \InvalidArgumentException(__('db.:name exceeds the remaining quantity of :qty.', [
                    'name' => $byId[$id]['name'],
                    'qty' => self::formatQty($remaining),
                ]));
            }
            $accepted[] = [
                'product_sale_id' => $id,
                'product_id' => $byId[$id]['product_id'],
                'qty' => $qty,
            ];
        }

        if (!$accepted) {
            throw new \InvalidArgumentException(__('db.Select at least one product and a quantity that is still remaining.'));
        }

        return $accepted;
    }

    /**
     * Normalize a delivery quantity payload.
     *
     * The add-delivery form posts deliver_qty[product_sale_id] => qty. PHP
     * collapses a repeated key, so that shape cannot express a duplicate.
     * A list of {product_sale_id, qty} rows keeps a repeated product so the
     * same-delivery guard above can reject it before anything is stored.
     *
     * @param  array<int|string, mixed>  $quantities
     * @return array<int, array{product_sale_id:int, qty:mixed}>
     */
    public static function quantityRows(array $quantities): array
    {
        $rows = [];
        foreach ($quantities as $key => $value) {
            if (is_array($value) && array_key_exists('product_sale_id', $value)) {
                $rows[] = [
                    'product_sale_id' => (int) $value['product_sale_id'],
                    'qty' => $value['qty'] ?? null,
                ];
                continue;
            }
            if (is_array($value)) {
                throw new \InvalidArgumentException(__('db.Delivery quantity must be a number.'));
            }
            $rows[] = [
                'product_sale_id' => (int) $key,
                'qty' => $value,
            ];
        }

        return $rows;
    }

    public static function saleUsesTrackedLines(int $saleId): bool
    {
        return DB::table('delivery_products')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_products.delivery_id')
            ->where('deliveries.sale_id', $saleId)
            ->exists();
    }
}

<?php

use App\Models\Translation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $french = [
            'Active Date Format' => 'Format de date actif',
            'Active Logo Height Width' => 'Dimensions du logo actif',
            'Active Primary Color' => 'Couleur principale active',
            'Allow customers to order' => 'Autoriser les clients à commander',
            'Bill No' => 'N° de facture',
            'Bug No' => 'N° de bug',
            'Card Holder Name' => 'Nom du titulaire de la carte',
            'Card Number' => 'Numéro de carte',
            'Card Type' => 'Type de carte',
            'Choose Biller' => 'Choisir un vendeur',
            'Clear' => 'Effacer',
            'We' => 'Me',
            'Font' => 'Police',
            'Invoice Generate Setting' => 'Paramètres de génération de facture',
            'Is Default' => 'Par défaut',
            'Place Order' => 'Passer la commande',
            'Received By Info' => 'Informations du réceptionnaire',
            'Records' => 'Enregistrements',
            'Scan/Search product by name/code' => 'Scanner ou rechercher un produit par nom ou code',
            'Show Barcode' => 'Afficher le code-barres',
            'Show Bill TO Info' => 'Afficher le destinataire',
            'Show Footer Text' => 'Afficher le pied de page',
            'Show In Words' => 'Afficher le montant en lettres',
            'Show Payment Note' => 'Afficher la note de paiement',
            'Show QR Code' => 'Afficher le code QR',
            'Show Reference No' => 'Afficher la référence',
            'Show Warehouse Info' => 'Afficher l\'entrepôt',
            'Table' => 'Table',
            'Tables' => 'Tables',
            'Type envato purchase code for eCommerce addon' => 'Saisir le code d\'achat Envato du module e-commerce',
            'Type envato purchase code for WooCommerce addon' => 'Saisir le code d\'achat Envato du module WooCommerce',
            'Update Table' => 'Mettre à jour la table',
            'Wrong purchase code!' => 'Code d\'achat incorrect.',
            'Zip / Postal Code' => 'Code postal',
            'eCommerce add-on installed successfully!' => 'Module e-commerce installé avec succès.',
            'og Description' => 'Description Open Graph',
            'Og_Title' => 'Titre Open Graph',
            'Og_description' => 'Description Open Graph',
            'Og_Image' => 'Image Open Graph',
            'Woocommerce addon installed successfully!' => 'Module WooCommerce installé avec succès.',
        ];

        $now = now();
        foreach ($french as $key => $value) {
            $existing = DB::table('translations')
                ->where('locale', 'fr')
                ->where('group', 'db')
                ->where('key', $key)
                ->first();

            if (!$existing) {
                DB::table('translations')->insert([
                    'locale' => 'fr',
                    'group' => 'db',
                    'key' => $key,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                continue;
            }

            if ((string) $existing->value !== $value) {
                DB::table('translations')->where('id', $existing->id)->update([
                    'value' => $value,
                    'updated_at' => $now,
                ]);
            }
        }

        Translation::forgetCachedTranslations();
    }

    public function down(): void
    {
        Translation::forgetCachedTranslations();
    }
};

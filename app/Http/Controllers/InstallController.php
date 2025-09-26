<?php

namespace App\Http\Controllers;
use App\Http\Requests\InstallationRequest;
use App\Traits\ENVFilePutContent;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class InstallController extends Controller
{
    use ENVFilePutContent;

    public function installStep1()
    {
        return view('install.step_1');
    }

    public function installStep2()
    {
        return view('install.step_2');
    }
    public function installStep3()
    {
        return view('install.step_3');
    }

    public function installProcess(InstallationRequest $request)
    {
        $dataServer = self::purchaseVerify($request->purchasecode);

        if (!$dataServer->dbdata) {
            return redirect()->back()->withErrors(['errors' => ['Wrong Purchase Code !']]);
        }

        $envPath = base_path('.env');
        if (!file_exists($envPath))
            return redirect()->back()->withErrors(['errors' => ['.env file does not exist.']]);
        elseif (!is_readable($envPath))
            return redirect()->back()->withErrors(['errors' => ['.env file is not readable.']]);
        elseif (!is_writable($envPath))
            return redirect()->back()->withErrors(['errors' => ['.env file is not writable.']]);
        else {
            try {
                $this->envSetDatabaseCredentials($request);
                self::switchToNewDatabaseConnection($request);
                self::importCentralDatabase($dataServer->dbdata);
                self::optimizeClear();
                return redirect(url('/install/step-4'));

            } catch (Exception $e) {

                return redirect()->back()->withErrors(['errors' => [$e->getMessage()]]);
            }
        }
    }

    protected static function purchaseVerify(string $purchaseCode) : object
    {
        $post_string = urlencode($purchaseCode);
        $url = 'https://lion-coders.com/api/sale-pro-purchase/verify/install/'.$post_string;
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $response = json_decode($result, false);

        return $response;

    }

    protected function envSetDatabaseCredentials($request): void
    {
        $this->dataWriteInENVFile('APP_URL', url('/'));
        $this->dataWriteInENVFile('DB_HOST', $request->db_host);
        $this->dataWriteInENVFile('DB_DATABASE', $request->db_name);
        $this->dataWriteInENVFile('DB_USERNAME', $request->db_username);
        $this->dataWriteInENVFile('DB_PASSWORD', $request->db_password);
    }

    public function switchToNewDatabaseConnection($request): void
    {
        DB::purge('mysql');
        Config::set('database.connections.mysql.host', $request->db_host);
        Config::set('database.connections.mysql.database', $request->db_name);
        Config::set('database.connections.mysql.username', $request->db_username);
        Config::set('database.connections.mysql.password', $request->db_password);
    }

    protected static function importCentralDatabase($dbdata): void
    {
        DB::unprepared($dbdata);
    }

    protected static function optimizeClear(): void
    {
        Artisan::call('optimize:clear');
    }

    public function installStep4()
    {
        return view('install.step_4');
    }

}

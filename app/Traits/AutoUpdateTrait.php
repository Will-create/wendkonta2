<?php
namespace App\Traits;

trait AutoUpdateTrait{
    /*
    |============================================================
    | # For Version Upgrade - you should follow these point in DEMO :
    |       1. clientVersionNumber >= minimumRequiredVersion
    |       2. latestVersionUpgradeEnable === true
    |       3. demoVersionNumber > clientVersionNumber
    |
    |===========================================================
    */
    public function isUpdateAvailable()
    {
        $versionUpgradeData = [];
        $url = config('database.connections.saleprosaas_landlord')
                ? 'https://lion-coders.com/api/sale-pro-saas-purchase/verify/updatecheck'
                : 'https://lion-coders.com/api/sale-pro-purchase/verify/updatecheck';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, false);

        $isServerConnectionOk = isset($data) && !empty($data) ? true : false;

        if ($isServerConnectionOk) {
            $clientVersionNumber = $this->stringToNumberConvert(env('VERSION'));
            $demoVersionNumber      = $this->stringToNumberConvert($data->demo_version);
            $minimumRequiredVersion = $this->stringToNumberConvert($data->minimum_required_version);
            $versionUpgradeData['alert_version_upgrade_enable'] = false;
            if ($demoVersionNumber > $clientVersionNumber && $clientVersionNumber >= $minimumRequiredVersion) {
                $versionUpgradeData['alert_version_upgrade_enable'] = true;
            }
            $versionUpgradeData['demo_version'] = $data->demo_version;
            $versionUpgradeData['latest_version_db_migrate_enable'] = $data->latest_version_db_migrate_enable;
            $versionUpgradeData['advertise_info'] = $data->advertise_info;
        };

        return $versionUpgradeData;
    }

    private function stringToNumberConvert($dataString) {
        $myArray = explode(".", $dataString);
        $versionString = "";
        foreach($myArray as $element) {
          $versionString .= $element;
        }
        $versionConvertNumber = intval($versionString);
        return $versionConvertNumber;
    }

    public function versionUpgradeFileUrl($purchaseCode)
    {
        $version_upgrade_file_url = null;
        $post_string = urlencode($purchaseCode);
        $url = config('database.connections.saleprosaas_landlord')
                ? 'https://lion-coders.com/api/sale-pro-saas-purchase/verify/updatefile/'.$post_string
                : 'https://lion-coders.com/api/sale-pro-purchase/verify/updatefile/'.$post_string;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, false);

        $isServerConnectionOk = isset($data) && !empty($data) ? true : false;

        if ($isServerConnectionOk) {
            $version_upgrade_file_url = $data->version_upgrade_file_url;
        };

        return $version_upgrade_file_url;
    }
}

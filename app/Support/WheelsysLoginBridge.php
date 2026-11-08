<?php

namespace App\Support;

use App\Models\EmailSetting;
use RuntimeException;

/**
 * Builds an auto-submit HTML form that signs into Wheelsys in the browser.
 */
final class WheelsysLoginBridge
{
    public const NEW_TRAFFIC_VIOLATION_PATH = '/ui/manage/master/trafficviolation.aspx';

    /**
     * Company → Import Data (override via WHEELSYS_IMPORT_PATH).
     * User still chooses Traffic violation import type in Wheels UI.
     */
    public const IMPORT_DATA_PATH = '/ui/manage/tasks/import.aspx';

    /**
     * Deep-link to an existing Traffic Violation in Wheelsys (`entityId` = Wheels Id).
     */
    public static function trafficViolationUrl(string|int $entityId): string
    {
        $id = trim((string) $entityId);
        $base = rtrim(EmailSetting::wheelsysConfig()['base_url'], '/');
        if ($base === '' || $id === '') {
            return '';
        }

        return $base.self::NEW_TRAFFIC_VIOLATION_PATH.'?entityId='.rawurlencode($id);
    }

    /**
     * @return array{action: string, fields: array<string, string>}
     */
    public static function buildFormPayload(?string $returnUrl = null): array
    {
        $creds = EmailSetting::wheelsysConfig();
        $baseUrl = $creds['base_url'];
        $returnUrl = self::sanitizeReturnUrl(
            $returnUrl ?: (string) config('services.wheelsys.return_url', '/ui/dashboards/dailyview.aspx')
        );
        $username = $creds['username'];
        $password = $creds['password'];

        if ($baseUrl === '' || $username === '' || $password === '') {
            throw new RuntimeException('Wheelsys credentials are not configured. Set them in Settings → System settings (Wheelsys) or WHEELSYS_* in .env.');
        }

        $loginUrl = $baseUrl.'/sign-in/default.aspx?ReturnUrl='.rawurlencode($returnUrl);

        $html = self::httpGet($loginUrl);

        if (! preg_match('/name="__VIEWSTATE"[^>]*value="([^"]*)"/', $html, $viewState)) {
            throw new RuntimeException('Could not read Wheelsys login form (__VIEWSTATE).');
        }

        if (! preg_match('/name="__VIEWSTATEGENERATOR"[^>]*value="([^"]*)"/', $html, $viewStateGenerator)) {
            throw new RuntimeException('Could not read Wheelsys login form (__VIEWSTATEGENERATOR).');
        }

        return [
            'action' => $loginUrl,
            'fields' => [
                '__VIEWSTATE' => $viewState[1],
                '__VIEWSTATEGENERATOR' => $viewStateGenerator[1],
                'ctl00$coreBody$hdfPwd' => base64_encode($password),
                'ctl00$coreBody$hdfUpdatingPassword' => '0',
                'ctl00$coreBody$hdfEmail' => '',
                'ctl00$coreBody$FortNoxStateHidden' => '',
                'ctl00$coreBody$FortNoxStationHidden' => '',
                'tbEmail_text' => $username,
                'tbPassword_text' => '',
                'tbMFA_text' => '',
                'MFAUserId' => '',
                'hdConnectStation' => '',
                'hdConnectPos' => '',
                'ctl00$coreBody$btnActualSignin' => 'Sign-in',
            ],
        ];
    }

    public static function sanitizeReturnUrl(string $returnUrl): string
    {
        $returnUrl = trim($returnUrl);
        if ($returnUrl === '') {
            return '/ui/dashboards/dailyview.aspx';
        }

        // Only allow relative Wheelsys UI paths.
        if (! str_starts_with($returnUrl, '/ui/')) {
            return '/ui/dashboards/dailyview.aspx';
        }

        if (str_contains($returnUrl, '://') || str_contains($returnUrl, '..')) {
            return '/ui/dashboards/dailyview.aspx';
        }

        return $returnUrl;
    }

    private static function httpGet(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SitGo-VIO/1.0)',
        ]);

        $html = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($html === false || $status >= 400) {
            throw new RuntimeException('Failed to load Wheelsys login page'.($error !== '' ? ": {$error}" : " (HTTP {$status})."));
        }

        return $html;
    }
}

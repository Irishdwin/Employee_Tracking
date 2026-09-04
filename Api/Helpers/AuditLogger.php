<?php
// Employee_Tracking/Api/Helpers/AuditLogger.php

class AuditLogger {

    /**
     * Inserts an audit log entry into system_logs.
     */
    public static function log($db, string $module, string $action, string $description, ?string $referenceId = null): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? $_SESSION['applicant_id'] ?? null;
        $username = $_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'System Administrator';
        $role = $_SESSION['role'] ?? 'Administrator';

        $ipAddress = self::getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browser = self::getBrowser($userAgent);
        $os = self::getOS($userAgent);
        $device = self::getDevice($userAgent);

        try {
            if ($db instanceof mysqli) {
                $stmt = $db->prepare("
                    INSERT INTO system_logs 
                    (user_id, username, role, module, action, description, reference_id, ip_address, browser, operating_system, device)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ref = $referenceId ?? '';
                $uId = $userId ? (int)$userId : null;
                $stmt->bind_param("issssssssss", $uId, $username, $role, $module, $action, $description, $ref, $ipAddress, $browser, $os, $device);
                $res = $stmt->execute();
                $stmt->close();
                return $res;
            } else if ($db instanceof PDO) {
                $stmt = $db->prepare("
                    INSERT INTO system_logs 
                    (user_id, username, role, module, action, description, reference_id, ip_address, browser, operating_system, device)
                    VALUES (:user_id, :username, :role, :module, :action, :description, :reference_id, :ip, :browser, :os, :device)
                ");

                return $stmt->execute([
                    ':user_id'      => $userId,
                    ':username'     => $username,
                    ':role'         => $role,
                    ':module'       => $module,
                    ':action'       => $action,
                    ':description'  => $description,
                    ':reference_id' => $referenceId,
                    ':ip'           => $ipAddress,
                    ':browser'      => $browser,
                    ':os'           => $os,
                    ':device'       => $device
                ]);
            }
        } catch (Exception $e) {
            error_log("AuditLogger Error: " . $e->getMessage());
            return false;
        }
        return false;
    }

    private static function getClientIp(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private static function getOS(string $userAgent): string {
        $osPlatform = "Windows 10/11";
        $osArray = [
            '/windows nt 10/i'      => 'Windows 10/11',
            '/windows nt 6.3/i'     => 'Windows 8.1',
            '/macintosh|mac os x/i' => 'Mac OS X',
            '/linux/i'              => 'Linux',
            '/ubuntu/i'             => 'Ubuntu',
            '/iphone/i'             => 'iPhone OS',
            '/android/i'            => 'Android'
        ];
        foreach ($osArray as $regex => $value) {
            if (preg_match($regex, $userAgent)) {
                $osPlatform = $value;
                break;
            }
        }
        return $osPlatform;
    }

    private static function getBrowser(string $userAgent): string {
        $browser = "Chrome";
        $browserArray = [
            '/msie/i'      => 'Internet Explorer',
            '/firefox/i'   => 'Firefox',
            '/safari/i'    => 'Safari',
            '/chrome/i'    => 'Chrome',
            '/edge/i'      => 'Edge',
            '/opera/i'     => 'Opera'
        ];
        foreach ($browserArray as $regex => $value) {
            if (preg_match($regex, $userAgent)) {
                $browser = $value;
                break;
            }
        }
        return $browser;
    }

    private static function getDevice(string $userAgent): string {
        if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobile))/i', $userAgent)) {
            return 'Tablet';
        }
        if (preg_match('/(android|bb\d+|meego).+mobile|iphone|ipod|blackberry|iemobile|opera mini/i', $userAgent)) {
            return 'Mobile';
        }
        return 'Desktop';
    }
}
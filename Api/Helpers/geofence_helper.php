<?php
/**
 * Geofence & Device Audit Helper
 */

if (!function_exists('calculateHaversineDistance')) {
    /**
     * Calculates distance between two GPS points in meters using the Haversine formula
     */
    function calculateHaversineDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000; // Earth radius in meters

        $dLat = deg2rad((float)$lat2 - (float)$lat1);
        $dLon = deg2rad((float)$lon2 - (float)$lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2); // Distance in meters
    }
}

if (!function_exists('parseUserAgentDetails')) {
    /**
     * Parses browser name, OS, and device type from User-Agent string
     */
    function parseUserAgentDetails($userAgent) {
        $os = "Unknown OS";
        $browser = "Unknown Browser";
        $device = "Desktop";

        if (preg_match('/mobile/i', $userAgent)) {
            $device = "Mobile";
        } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
            $device = "Tablet";
        }

        // Operating System
        if (preg_match('/windows nt 10/i', $userAgent)) $os = 'Windows 10/11';
        elseif (preg_match('/macintosh|mac os x/i', $userAgent)) $os = 'Mac OS X';
        elseif (preg_match('/android/i', $userAgent)) $os = 'Android';
        elseif (preg_match('/iphone|ipad/i', $userAgent)) $os = 'iOS';
        elseif (preg_match('/linux/i', $userAgent)) $os = 'Linux';

        // Browser
        if (preg_match('/edg/i', $userAgent)) $browser = 'Edge';
        elseif (preg_match('/chrome/i', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/safari/i', $userAgent)) $browser = 'Safari';
        elseif (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';

        return [
            'browser' => $browser,
            'os' => $os,
            'device' => $device,
            'user_agent' => $userAgent
        ];
    }
}

if (!function_exists('saveBase64Selfie')) {
    /**
     * Decodes Base64 camera string and saves JPEG image to server uploads
     */
    function saveBase64Selfie($base64Data, $userId, $prefix = 'clockin') {
        if (!is_string($base64Data) || strlen($base64Data)>7000000 || !preg_match('/^data:image\/(jpeg|png);base64,/', $base64Data)) return null;
        if (!preg_match('/^[a-z_]+$/i',(string)$prefix) || (int)$userId<=0) return null;
        $data=base64_decode(substr($base64Data,strpos($base64Data,',')+1),true);
        if ($data===false || strlen($data)>5*1024*1024) return null;
        $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($data);
        $info=@getimagesizefromstring($data);
        if (!$info || !in_array($mime,['image/jpeg','image/png'],true) || $info[0]*$info[1]>20000000) return null;
        $uploadDir=__DIR__.'/../../uploads/attendance_photos/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir,0750,true)) return null;
        $filename=$prefix.'_'.(int)$userId.'_'.bin2hex(random_bytes(16)).($mime==='image/png'?'.png':'.jpg');
        if (file_put_contents($uploadDir.$filename,$data,LOCK_EX)!==false) return 'uploads/attendance_photos/'.$filename;

        return null;
    }
}
<?php
/**
 * Resolve registered work-site geofence for an employee's active deployment.
 */

require_once __DIR__ . '/geofence_helper.php';

if (!function_exists('geofence_coords_are_valid')) {
    function geofence_coords_are_valid($lat, $lng): bool
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return false;
        }
        $lat = (float)$lat;
        $lng = (float)$lng;
        if ($lat === 0.0 && $lng === 0.0) {
            return false;
        }
        return abs($lat) <= 90.0 && abs($lng) <= 180.0;
    }
}

if (!function_exists('geofence_pick_coordinates')) {
    /**
     * Prefer TL-registered coordinates, then legacy latitude/longitude columns.
     *
     * @param array<string,mixed> $row
     * @return array{latitude:float,longitude:float,source:string}|null
     */
    function geofence_pick_coordinates(array $row, string $prefix): ?array
    {
        $pairs = [
            ['registered_latitude', 'registered_longitude', $prefix . '_registered'],
            ['latitude', 'longitude', $prefix . '_latitude'],
        ];
        foreach ($pairs as [$latSuffix, $lngSuffix, $source]) {
            $latKey = $prefix . '_' . $latSuffix;
            $lngKey = $prefix . '_' . $lngSuffix;
            $lat = $row[$latKey] ?? null;
            $lng = $row[$lngKey] ?? null;
            if (geofence_coords_are_valid($lat, $lng)) {
                return [
                    'latitude' => (float)$lat,
                    'longitude' => (float)$lng,
                    'source' => $source,
                ];
            }
        }
        return null;
    }
}

if (!function_exists('work_site_resolve_for_user')) {
    /**
     * @return array<string,mixed>|null
     */
    function work_site_resolve_for_user(mysqli $conn, int $userId): ?array
    {
        $sql = "
            SELECT
                d.DeploymentID,
                d.CompanyID,
                d.SiteSuperVisorID,
                d.Location AS deployment_location,
                dep_co.Name AS company_name,
                dep_co.registered_latitude AS dep_registered_latitude,
                dep_co.registered_longitude AS dep_registered_longitude,
                dep_co.latitude AS dep_latitude,
                dep_co.longitude AS dep_longitude,
                dep_co.geofence_radius AS dep_geofence_radius,
                tl_co.CompanyID AS tl_company_id,
                tl_co.Name AS tl_company_name,
                tl_co.registered_latitude AS tl_registered_latitude,
                tl_co.registered_longitude AS tl_registered_longitude,
                tl_co.latitude AS tl_latitude,
                tl_co.longitude AS tl_longitude,
                tl_co.geofence_radius AS tl_geofence_radius,
                e.EmployeeID,
                e.EmployeeNumber,
                tl.FullName AS team_lead_name
            FROM users u
            INNER JOIN employee e ON e.Email = u.Email AND e.IsActive = 1
            INNER JOIN deployment d ON d.EmployeeID = e.EmployeeID
                AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
            INNER JOIN clientcompany dep_co ON dep_co.CompanyID = d.CompanyID AND dep_co.IsActive = 1
            LEFT JOIN users tl ON tl.UsersID = d.SiteSuperVisorID
            LEFT JOIN clientcompany tl_co ON tl_co.UserID = d.SiteSuperVisorID AND tl_co.IsActive = 1
            WHERE u.UsersID = ?
            ORDER BY d.StartDate DESC, d.DeploymentID DESC
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        $picked = geofence_pick_coordinates($row, 'dep');
        $source = 'deployment_company';
        if ($picked === null && !empty($row['tl_company_id'])) {
            $picked = geofence_pick_coordinates($row, 'tl');
            $source = 'team_lead_company';
        }

        $radius = (int)($row['dep_geofence_radius'] ?? 0);
        if ($radius <= 0 && !empty($row['tl_geofence_radius'])) {
            $radius = (int)$row['tl_geofence_radius'];
        }
        if ($radius <= 0) {
            $radius = 100;
        }

        return [
            'deployment_id' => (int)$row['DeploymentID'],
            'employee_id' => (int)$row['EmployeeID'],
            'company_id' => (int)$row['CompanyID'],
            'company_name' => (string)($row['company_name'] ?? ''),
            'deployment_location' => (string)($row['deployment_location'] ?? ''),
            'team_lead_name' => (string)($row['team_lead_name'] ?? ''),
            'site_supervisor_id' => isset($row['SiteSuperVisorID']) ? (int)$row['SiteSuperVisorID'] : null,
            'site_latitude' => $picked['latitude'] ?? null,
            'site_longitude' => $picked['longitude'] ?? null,
            'geofence_radius' => $radius,
            'coordinate_source' => $picked['source'] ?? null,
            'site_registered' => $picked !== null,
        ];
    }
}

if (!function_exists('geofence_validate_employee_position')) {
    /**
     * @return array{allowed:bool,distance_meters:float,message:string,site:array<string,mixed>}
     */
    function geofence_validate_employee_position(
        mysqli $conn,
        int $userId,
        float $employeeLat,
        float $employeeLng
    ): array {
        $site = work_site_resolve_for_user($conn, $userId);
        if (!$site) {
            return [
                'allowed' => false,
                'distance_meters' => 0.0,
                'message' => 'You are not assigned to an active deployment site.',
                'site' => [],
            ];
        }

        if (!$site['site_registered']
            || !geofence_coords_are_valid($site['site_latitude'], $site['site_longitude'])) {
            return [
                'allowed' => false,
                'distance_meters' => 0.0,
                'message' => 'Your Team Leader has not registered the official Work Site coordinates yet.',
                'site' => $site,
            ];
        }

        if (!geofence_coords_are_valid($employeeLat, $employeeLng)) {
            return [
                'allowed' => false,
                'distance_meters' => 0.0,
                'message' => 'Valid GPS location is required to verify attendance.',
                'site' => $site,
            ];
        }

        $siteLat = (float)$site['site_latitude'];
        $siteLng = (float)$site['site_longitude'];
        $radius = (float)$site['geofence_radius'];

        $distanceMeters = calculateHaversineDistance(
            $employeeLat,
            $employeeLng,
            $siteLat,
            $siteLng
        );

        if ($distanceMeters > $radius) {
            return [
                'allowed' => false,
                'distance_meters' => $distanceMeters,
                'message' => sprintf(
                    'You are outside the allowed work site. (Distance: %.2fm, Max Radius: %.0fm)',
                    $distanceMeters,
                    $radius
                ),
                'site' => $site,
            ];
        }

        return [
            'allowed' => true,
            'distance_meters' => $distanceMeters,
            'message' => sprintf('Inside geofence (%.2fm from site).', $distanceMeters),
            'site' => $site,
        ];
    }
}

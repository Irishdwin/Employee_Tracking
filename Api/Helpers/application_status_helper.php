<?php
/**
 * Canonical 5-stage recruitment workflow for job_applications.
 *
 * Stages: Applied -> Scheduled for Interview -> Endorsed to Client
 *         -> Approved by Company -> Hired
 */

if (!function_exists('ash_fmt_date')) {
    function ash_fmt_date($d, $withTime = false)
    {
        if ($d === null) {
            return null;
        }
        $d = trim((string)$d);
        if ($d === '' || $d === '0000-00-00') {
            return null;
        }
        $ts = strtotime($d);
        if ($ts === false) {
            return null;
        }
        return date($withTime ? 'M d, Y H:i' : 'M d, Y', $ts);
    }
}

if (!function_exists('ash_resolve_stage')) {
    /**
     * @return string applied|scheduled_for_interview|endorsed_to_client|approved_by_company|rejected|hired
     */
    function ash_resolve_stage(array $row): string
    {
        if (!empty($row['HiredDate'])) {
            return 'hired';
        }

        $clientStatus = trim((string)($row['ClientApprovalStatus'] ?? ''));
        if ($clientStatus === 'Approved') {
            return 'approved_by_company';
        }
        if ($clientStatus === 'Rejected') {
            return 'rejected';
        }

        if (!empty($row['EndorsedDate'])) {
            return 'endorsed_to_client';
        }

        if (!empty($row['InterviewDate'])) {
            return 'scheduled_for_interview';
        }

        $statusName = trim((string)($row['StatusFromTable'] ?? $row['Status'] ?? ''));
        if ($statusName === 'For Interview' || $statusName === 'Scheduled for Interview') {
            return 'scheduled_for_interview';
        }
        if ($statusName === 'Endorsed' || $statusName === 'Endorsed to Client') {
            return 'endorsed_to_client';
        }
        if ($statusName === 'Hired') {
            return 'hired';
        }

        return 'applied';
    }
}

if (!function_exists('ash_stage_display_label')) {
    function ash_stage_display_label(string $stage): string
    {
        $map = [
            'applied' => 'Applied',
            'scheduled_for_interview' => 'Scheduled for Interview',
            'endorsed_to_client' => 'Endorsed to Client',
            'approved_by_company' => 'Approved by Company',
            'rejected' => 'Declined',
            'hired' => 'Hired',
        ];
        return $map[$stage] ?? 'Applied';
    }
}

if (!function_exists('ash_admin_badge_bootstrap')) {
    function ash_admin_badge_bootstrap(string $label): string
    {
        $map = [
            'Applied' => 'secondary',
            'Scheduled for Interview' => 'info',
            'Endorsed to Client' => 'primary',
            'Approved by Company' => 'warning',
            'Declined' => 'danger',
            'Hired' => 'success',
        ];
        return $map[$label] ?? 'secondary';
    }
}

if (!function_exists('ash_applicant_badge_bootstrap')) {
    function ash_applicant_badge_bootstrap(string $label): string
    {
        $map = [
            'Applied' => 'secondary',
            'Scheduled for Interview' => 'info',
            'Endorsed to Client' => 'primary',
            'Approved by Company' => 'warning',
            'Hired' => 'success',
            'Declined' => 'danger',
        ];
        return $map[$label] ?? 'secondary';
    }
}

if (!function_exists('ash_resolve_display_status')) {
    function ash_resolve_display_status(array $row): string
    {
        return ash_stage_display_label(ash_resolve_stage($row));
    }
}

if (!function_exists('ash_build_timeline')) {
    function ash_build_timeline(array $row): array
    {
        $timeline = [];

        $timeline[] = [
            'key' => 'applied',
            'label' => 'Applied',
            'date' => ash_fmt_date($row['ApplicationDate'] ?? null, false),
            'detail' => null,
        ];

        if (!empty($row['InterviewDate'])) {
            $timeline[] = [
                'key' => 'scheduled_for_interview',
                'label' => 'Scheduled for Interview',
                'date' => ash_fmt_date($row['InterviewDate'], true),
                'detail' => isset($row['InterviewNotes']) && trim((string)$row['InterviewNotes']) !== ''
                    ? (string)$row['InterviewNotes']
                    : null,
            ];
        }

        if (!empty($row['EndorsedDate'])) {
            $timeline[] = [
                'key' => 'endorsed_to_client',
                'label' => 'Endorsed to Client',
                'date' => ash_fmt_date($row['EndorsedDate'], true),
                'detail' => null,
            ];
        }

        $clientStatus = trim((string)($row['ClientApprovalStatus'] ?? ''));
        if ($clientStatus === 'Approved') {
            $timeline[] = [
                'key' => 'approved_by_company',
                'label' => 'Approved by Company',
                'date' => ash_fmt_date($row['ClientApprovalDate'] ?? null, false),
                'detail' => null,
            ];
        } elseif ($clientStatus === 'Rejected') {
            $timeline[] = [
                'key' => 'rejected',
                'label' => 'Declined',
                'date' => ash_fmt_date($row['ClientApprovalDate'] ?? null, false),
                'detail' => null,
            ];
        }

        if (!empty($row['HiredDate'])) {
            $timeline[] = [
                'key' => 'hired',
                'label' => 'Hired',
                'date' => ash_fmt_date($row['HiredDate'], true),
                'detail' => null,
            ];
        }

        return $timeline;
    }
}

if (!function_exists('ash_workflow_step_index')) {
    /** 0-based index for the 5-stage applicant stepper. */
    function ash_workflow_step_index(string $badgeLabel): int
    {
        $map = [
            'Applied' => 0,
            'Scheduled for Interview' => 1,
            'Endorsed to Client' => 2,
            'Approved by Company' => 3,
            'Hired' => 4,
            'Declined' => 2,
        ];
        return $map[$badgeLabel] ?? 0;
    }
}

if (!function_exists('ash_get_or_create_status_id')) {
    /**
     * @param string[] $legacyNames
     */
    function ash_get_or_create_status_id(mysqli $conn, string $canonicalName, array $legacyNames = []): ?int
    {
        $names = array_values(array_unique(array_merge([$canonicalName], $legacyNames)));

        foreach ($names as $name) {
            $stmt = $conn->prepare('SELECT StatusID FROM status WHERE Name = ? LIMIT 1');
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            if ($row) {
                return (int)$row['StatusID'];
            }
        }

        $stmt = $conn->prepare('INSERT INTO status (Name) VALUES (?)');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $canonicalName);
        if ($stmt->execute()) {
            $id = (int)$conn->insert_id;
            $stmt->close();
            return $id > 0 ? $id : null;
        }
        $stmt->close();

        $stmt = $conn->prepare('SELECT StatusID FROM status WHERE Name = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $canonicalName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ? (int)$row['StatusID'] : null;
    }
}

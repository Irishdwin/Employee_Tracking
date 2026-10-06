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
        return date($withTime ? 'M d, Y g:i A' : 'M d, Y', $ts);
    }
}

if (!function_exists('ash_resolve_stage')) {
    /**
     * @return string applied|scheduled_for_interview|endorsed_to_client|approved_by_company|client_rejected|rejected|hired
     */
    function ash_resolve_stage(array $row): string
    {
        if (!empty($row['HiredDate'])) {
            return 'hired';
        }

        $agencyStatus = trim((string)($row['StatusFromTable'] ?? $row['Status'] ?? ''));
        if (in_array($agencyStatus, ['Rejected','Declined','Failed'], true)) return 'rejected';
        $clientStatus = trim((string)($row['ClientApprovalStatus'] ?? ''));
        if ($clientStatus === 'Approved') {
            return 'approved_by_company';
        }
        if ($clientStatus === 'Rejected') {
            return 'client_rejected';
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
            'client_rejected' => 'Rejected by Client',
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
            'Rejected by Client' => 'warning',
            'Client Review Unsuccessful — For Reassignment' => 'warning',
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
            'Rejected by Client' => 'warning',
            'Client Review Unsuccessful — For Reassignment' => 'warning',
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

if (!function_exists('ash_fmt_interview_time_12h')) {
    /**
     * Applicant-facing interview time only — 12-hour clock with AM/PM
     * (e.g. "Oct 02, 2026 5:30 PM"), per the ONAZUL HRIS Applicant UI
     * requirement. Deliberately separate from ash_fmt_date()'s own
     * $withTime=true 24-hour format, which Api/HR/get_application_details.php
     * still uses as-is for its own (unrelated, HR-facing) interview_date
     * field and is not touched here.
     */
    function ash_fmt_interview_time_12h($d): ?string
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
        return date('M d, Y g:i A', $ts);
    }
}

if (!function_exists('ash_build_timeline')) {
    /**
     * @param bool $applicantFacing When true, the "Scheduled for Interview"
     *        entry's date/time is shown in 12-hour AM/PM format for the
     *        Applicant UI (Api/Applicant/get_my_applications.php). Defaults
     *        to false so the existing HR-facing caller
     *        (Api/HR/get_application_details.php) keeps its current 24-hour
     *        display unchanged.
     * @param bool $newestFirst When true, returns the timeline newest-event
     *        first (the order chronologically-built timeline entries are
     *        reversed) for the Applicant "Status Updates" UI. Defaults to
     *        false to leave the existing (currently unused) HR-facing
     *        timeline field's order unchanged.
     */
    function ash_build_timeline(array $row, bool $applicantFacing = false, bool $newestFirst = false): array
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
                'date' => $applicantFacing
                    ? ash_fmt_interview_time_12h($row['InterviewDate'])
                    : ash_fmt_date($row['InterviewDate'], true),
                'detail' => isset($row['InterviewNotes']) && trim((string)$row['InterviewNotes']) !== ''
                    ? (string)$row['InterviewNotes']
                    : null,
            ];
        }

        if (!empty($row['EndorsementAttempts'])) {
            foreach ($row['EndorsementAttempts'] as $attempt) {
                $timeline[] = ['key'=>'endorsed_to_client', 'label'=>(int)$attempt['AttemptNumber']>1 ? 'Re-Endorsed to Client' : 'Endorsed to Client',
                    'date'=>ash_fmt_date($attempt['EndorsedAt'],true), 'detail'=>$attempt['CompanyName'].' · '.$attempt['PositionTitle']];
                if ($attempt['Decision']!=='Pending') $timeline[] = ['key'=>$attempt['Decision']==='Approved'?'approved_by_company':'client_rejected',
                    'label'=>$attempt['Decision']==='Approved'?'Approved by Company':($applicantFacing?'Client Review Unsuccessful — For Reassignment':'Rejected by Client'),
                    'date'=>ash_fmt_date($attempt['DecidedAt'],true), 'detail'=>$attempt['CompanyName']];
            }
        } else {
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
                'key' => 'client_rejected',
                'label' => $applicantFacing ? 'Client Review Unsuccessful — For Reassignment' : 'Rejected by Client',
                'date' => ash_fmt_date($row['ClientApprovalDate'] ?? null, false),
                'detail' => null,
            ];
        }

        }

        if (!empty($row['HiredDate'])) {
            $timeline[] = [
                'key' => 'hired',
                'label' => 'Hired',
                'date' => ash_fmt_date($row['HiredDate'], true),
                'detail' => null,
            ];
        }

        // Attempts are supplied oldest-first; each endorsement precedes its decision.
        // Applied remains first and Hired last, preserving the existing timeline order.
        if ($newestFirst) {
            $timeline = array_reverse($timeline);
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
            'Rejected by Client' => 2,
            'Client Review Unsuccessful — For Reassignment' => 2,
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

<?php
/**
 * Server-side messenger RBAC (must match Js/messenger_rbac.js).
 */

function messenger_normalize_role(string $role): string
{
    $role = trim($role);
    $map = [
        'Accounting Staff' => 'Accounting',
        'Team Lead' => 'TeamLead',
    ];
    return $map[$role] ?? $role;
}

/** @return array<int, string>|string '*' */
function messenger_allowed_roles_for(string $viewerRole)
{
    $viewerRole = messenger_normalize_role($viewerRole);
    $rules = [
        'Admin' => ['HR', 'Accounting'],
        'HR' => '*',
        'Accounting' => ['HR', 'TeamLead'],
        'TeamLead' => ['Employee', 'Accounting', 'HR'],
        'Applicant' => ['HR'],
        'Employee' => ['TeamLead', 'HR'],
    ];
    return $rules[$viewerRole] ?? [];
}

function messenger_contact_allowed(
    string $viewerRole,
    int $viewerUserId,
    string $contactRole,
    int $contactUserId,
    ?int $contactTeamLeadId
): bool {
    if ($contactUserId === $viewerUserId) {
        return false;
    }

    $viewerRole = messenger_normalize_role($viewerRole);
    $contactRole = messenger_normalize_role($contactRole);
    $allowed = messenger_allowed_roles_for($viewerRole);

    if ($allowed === '*') {
        return true;
    }
    if (!is_array($allowed) || !in_array($contactRole, $allowed, true)) {
        return false;
    }

    if ($viewerRole === 'TeamLead' && $contactRole === 'Employee') {
        return $contactTeamLeadId !== null && $contactTeamLeadId === $viewerUserId;
    }

    return true;
}

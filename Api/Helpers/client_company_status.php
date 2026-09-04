<?php
/**
 * Keep clientcompany.IsActive in sync with active signed service agreements.
 * IsActive = 1 only when an Active agreement with uploaded signed document exists.
 */

function client_company_has_active_agreement_sql(string $companyAlias = 'c'): string {
    return "EXISTS (
        SELECT 1
        FROM serviceagreement sa
        WHERE sa.ClientCompanyID = {$companyAlias}.CompanyID
          AND sa.Status = 'Active'
          AND COALESCE(NULLIF(TRIM(sa.SignedDocumentPath), ''), '') <> ''
    )";
}

function sync_client_company_active_status(mysqli $conn, int $companyId): void {
    if ($companyId <= 0) {
        return;
    }
    $existsSql = client_company_has_active_agreement_sql('c');
    $sql = "UPDATE clientcompany c SET c.IsActive = IF({$existsSql}, 1, 0) WHERE c.CompanyID = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $stmt->close();
}

function sync_all_client_companies_active_status(mysqli $conn): void {
    $existsSql = client_company_has_active_agreement_sql('c');
    $conn->query("UPDATE clientcompany c SET c.IsActive = IF({$existsSql}, 1, 0)");
}

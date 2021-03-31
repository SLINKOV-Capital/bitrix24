<?php

    ini_set("log_errors", 1);
    ini_set("error_log", "./php-error.log");

    writeToLog($_REQUEST, 'incoming'); //comment it when everything will be debugged
    // 
    // This webhook should be called by Bitrix24 event '
    // Go to Your portal \ Developer resources \ Other \ Outbound webhook
    // or like it shown on the following screenshot: https://cloud.mail.ru/public/EmPS/BNuEV3s3p
    // 
    //=== CONSTANTS zone
    //
    define("UFRAS", "UF_CRM_XXXXXXXXX"); // The system name of custome field

    //=== VALIDATION & INITIALIZATION zone
    //Validates Webhook params and fill variables by Webhook params
    
    require __DIR__ . '/vendor/autoload.php';
    
    use \App\Bitrix24\Bitrix24API; // The library https://github.com/andrey-tech/bitrix24-api-php
    
    $webhookURL = 'https://[yourURLfromBitrix24]/';
    
    $bx24 = new Bitrix24API($webhookURL);
    
    $InvoiceID = $_REQUEST['data']['FIELDS']['ID'];
    
    //=== WORKING zone
    
    $InvoiceFields = $bx24->getInvoice($InvoiceID); //Get Invoice fields. 
    
    //If the Invoicee not just paid, then exit from the Webhook
    if ($InvoiceFields['PAYED'] != 'Y') {
        file_put_contents(getcwd() . '/hook.log', "\nInvvoice " . $InvoiceFields['ID'] . " is not paid \n", FILE_APPEND);
        exit;
    }
    
    if (!$Company) exit; //If no Company in Invoicee, exit.
    
    $Company = $InvoiceFields['UF_COMPANY_ID'];
    if (!$Company) exit; //If no Contact in Invoicee, exit.
    
    $CompanyFields = $bx24->getCompany($Company);
    
    //Which year is today?
    $CurrentYear = date("Y");
    $HappyThisYearString = $CurrentYear . '-01-01';
    $HappyThisYearTime = strtotime($HappyThisYearString);
    
    //=== WORKING ZONE
    
    //Summarize only this company's Invoice Amounts
    //First, let's get filtered invoices into an array
    $getInvoiceListGeneratorObject = $bx24->getInvoiceList(['ID' => 'ASC'],
    ['UF_COMPANY_ID' => $Company, 'PAYED' => 'Y'],
    ['ID', 'PRICE', 'DATE_PAYED', 'UF_DEAL_ID'],);
    //Invoice amounts into Array
    $InvoiceArray = array();
    foreach ($getInvoiceListGeneratorObject as $value) {
        $InvoiceArray = array_merge($InvoiceArray, $value);
    }
    
    //Summarize Invoice Amounts
    $m = count($InvoiceArray);
    if ($m == 0) {
        file_put_contents(getcwd() . '/hook.log', "\$no payments from this customer at all\n", FILE_APPEND);
        exit(json_encode(array('error' => 'no payments from this customer at all' )));
    }
    $RevenueCurrentYear = 0;
    for ($i = 0; $i < $m; $i++) {
        $t = strtotime($InvoiceArray[$i]['DATE_PAYED']);
        // file_put_contents(getcwd() . '/hook.log', "\n\$t = " . $t, FILE_APPEND);
        if ($t >= $HappyThisYearTime) {
            $RevenueCurrentYear += $InvoiceArray[$i]['PRICE'];
        }
    }
    if ($RevenueCurrentYear == 0) {
        file_put_contents(getcwd() . '/hook.log', "\n\$no payments from this customer this year\n", FILE_APPEND);
        exit(json_encode(array('error' => 'no payments from this customer this year' )));
    }
    
    $CompanyFields = $bx24->getCompany($Company);
    
    $bx24->updateCompany($Company, [UFRAS => $RevenueCurrentYear], ["REGISTER_SONET_EVENT" => "Y"]);
    
    //=== FUNCTIONS zone
    function writeToLog($data, $title = '') {
            $log = "\n------ max2SumInvoices.php ------------------\n";
            $log .= date("Y.m.d G:i:s") . "\n";
            // $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
            // $log .= print_r($data, 1);
            file_put_contents(getcwd() . '/hook.log', $log, FILE_APPEND);
            return true;
    }
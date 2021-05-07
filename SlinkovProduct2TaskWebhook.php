<?php

    ini_set("log_errors", 1);
    ini_set("error_log", "./php-error.log");

    // Validate params
    if (!(isset($_GET['id']) && isset($_GET['responsible']))) {
        exit(
            json_encode(array(
                'error' => 'id and responsible params is required' 
            ))
        );
    }

    require __DIR__ . '/vendor/autoload.php';

    use \App\Bitrix24\Bitrix24API;

    $webhookURL = 'your URL';

    $bx24 = new Bitrix24API($webhookURL);

    $id = $_GET['id'];
    $responsible = intval(str_replace('user_', '', $_GET['responsible']));

    $deal = $bx24->getDeal($id, ['PRODUCTS']);

    $deal_url = 'https://{your portal}/crm/deal/details/' . $deal['ID'] . '/';

    $contactExist = $deal['CONTACT_ID'] != 0 ? true : false;
    $companyExist = $deal['COMPANY_ID'] != 0 ? true : false; 


    try {
        $projectId = $bx24->request('lists.element.get', [
            'IBLOCK_TYPE_ID' => 'lists',
            'IBLOCK_ID' => '17'
        ])[0]['PROPERTY_69']['128'];
    } catch (Exception $e) {
        $projectId = '17'; //  TODO: to point default id
    }
    
    foreach ($deal['PRODUCTS'] as $product) {
        $title = '';

        if ($companyExist) {
            $company = $bx24->getCompany($deal['COMPANY_ID']);
    
            $title = $company['TITLE'] . ' - ' . $product['PRODUCT_NAME'];
        } elseif (!$companyExist && $contactExist) {
            $contact = $bx24->getContact($deal['CONTACT_ID']);
    
            $title = $contact['LAST_NAME'] . ' - ' . $product['PRODUCT_NAME'];
        } else {
            $title = $product['PRODUCT_NAME'];
        }

        // echo $title;
        $result = $bx24->addTask([
            'TITLE' => $title,
            'DESCRIPTION' => 'deal: ' . $deal_url,
            'RESPONSIBLE_ID' => $responsible,
            'GROUP_ID' => $projectId,
            'UF_TYPE_NEW' => false,
        ]);

        echo $product['PRODUCT_NAME'], ' task added';
    }

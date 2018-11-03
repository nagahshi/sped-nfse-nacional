<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../bootstrap.php';

use NFePHP\Common\Certificate;
use NFePHP\NFSeNac\Tools;
use NFePHP\NFSeNac\Common\Soap\SoapFake;
use NFePHP\NFSeNac\Common\FakePretty;

try {
    
    $config = [
        'cnpj' => '99999999000191',
        'im' => '1733160024',
        'cmun' => '4314902',
        'razao' => 'Empresa Test Ltda',
        'tpamb' => 2
    ];

    $configJson = json_encode($config);

    $content = file_get_contents('expired_certificate.pfx');
    $password = 'associacao';
    $cert = Certificate::readPfx($content, $password);
    
    $soap = new SoapFake();
    $soap->disableCertValidation(true);
    
    $tools = new Tools($configJson, $cert);
    $tools->loadSoapClass($soap);

    $dini = '2018-01-01';
    $dfim = '2018-10-31';
    $tomadorCnpj = '12345678901234';
    $tomadorCpf = null;
    $tomadorIM = null;

    $response = $tools->consultarNfse($dini, $dfim, $tomadorCnpj, $tomadorCpf, $tomadorIM);
    
    echo FakePretty::prettyPrint($response, '');
 
} catch (\Exception $e) {
    echo $e->getMessage();
}

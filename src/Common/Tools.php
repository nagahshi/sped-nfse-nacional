<?php

namespace NFePHP\NFSeNac\Common;

use NFePHP\Common\Certificate;
use NFePHP\NFSeNac\RpsInterface;
use NFePHP\Common\DOMImproved as Dom;
use NFePHP\NFSeNac\Common\Signer;

class Tools
{
    protected $config;
    protected $prestador;
    protected $certificate;
    protected $wsobj;
    protected $environment;
    public $lastRequest;
    
    protected $urls = [
        '4314902' => [
            'municipio' => 'Porto Alegre',
            'uf' => 'RS',
            'homologacao' => 'http://nfse-hom.procempa.com.br/nfe-ws',
            'producao' => 'http://nfe.portoalegre.rs.gov.br/nfe-ws',
            'version' => '1.00',
            'soapns' => 'http://ws.bhiss.pbh.gov.br'
        ],
        '3106200' => [
            'municipio' => 'Belo Horizonte',
            'uf' => 'MG',
            'homologacao' => 'https://bhisshomologa.pbh.gov.br/bhiss-ws/nfse',
            'producao' => 'https://bhissdigital.pbh.gov.br/bhiss-ws/nfse',
            'version' => '1.00',
            'soapns' => 'http://ws.bhiss.pbh.gov.br'
        ]
    ];
    
    public function __construct($config, Certificate $cert)
    {
        $this->config = json_decode($config);
        $this->certificate = $cert;
        $this->buildPrestadorTag();
        $wsobj = $this->urls;
        $this->wsobj = json_decode(json_encode($this->urls[$this->config->cmun]));
        $this->environment = 'homologacao';
        if ($this->config->tpamb === 1) {
            $this->environment = 'producao';
        }
    }
    
    protected function buildPrestadorTag()
    {
        $this->prestador = "<Prestador>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "</Prestador>";
    }


    public function sign($content, $tagname, $mark)
    {
        $xml = Signer::sign(
            $this->certificate,
            $content,
            $tagname,
            $mark
        );
        $dom = new Dom('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);
        return $dom->saveXML($dom->documentElement);
    }
    
    public function send($message, $operation)
    {
        $action = "{$this->wsobj->soapns}/$operation";
        $url = $this->wsobj->$this->environment;
        $request = $this->createSoapRequest($message, $operation);
        $this->lastRequest = $request;
        
        //TODO envio da mensagem SOAP para o webservice
    }
    
    protected function createSoapRequest($message, $operation)
    {
        return "<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">"
            . "<soap:body>"
            . "<ns2:{$operation}Request xmlns:ns2=\"{$this->wsobj->soapns}\">"
            . "<nfseCabecMsg>"
            . "<cabecalho xmlns=\"http://www.abrasf.org.br/nfse.xsd\" versao=\"{$this->wsobj->version}\">"
            . "<versaoDados>{$this->wsobj->version}</versaoDados>"
            . "</cabecalho></nfseCabecMsg>"
            . "<nfseDadosMsg>"
            . $message
            . "</nfseDadosMsg>"
            . "</ns2:{$operation}Request>"
            . "</soap:body>"
            . "</soap:Envelope>";
    }


    protected function putPrestadorInRps(RpsInterface $rps)
    {
        $dom = new Dom('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($rps->render());
        $referenceNode = $dom->getElementsByTagName('Servico')->item(0);
        $node = $dom->createElement('Prestador');
        $dom->addChild(
            $node,
            "Cnpj",
            $this->config->cnpj,
            true
        );
        $dom->addChild(
            $node,
            "InscricaoMunicipal",
            $this->config->im,
            true
        );
        $dom->insertAfter($node, $referenceNode);
        return $dom->saveXML($dom->documentElement);
    }
}

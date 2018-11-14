<?php

namespace NFePHP\NFSeNac\Common;

/**
 * Auxiar Tools Class for comunications with NFSe webserver in Nacional Standard
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeNac
 * @copyright NFePHP Copyright (c) 2008-2018
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-nfse-nacional for the canonical source repository
 */

use NFePHP\Common\Certificate;
use NFePHP\NFSeNac\RpsInterface;
use NFePHP\Common\DOMImproved as Dom;
use NFePHP\NFSeNac\Common\Signer;
use NFePHP\NFSeNac\Common\Soap\SoapInterface;
use NFePHP\NFSeNac\Common\Soap\SoapCurl;

class Tools
{
    public $lastRequest;
    
    protected $config;
    protected $prestador;
    protected $certificate;
    protected $wsobj;
    protected $soap;
    protected $environment;
    
    protected $urls = [
        '4314902' => [
            'municipio' => 'Porto Alegre',
            'uf' => 'RS',
            'homologacao' => 'https://nfse-hom.procempa.com.br/bhiss-ws/nfse',
            'producao' => 'https://nfe.portoalegre.rs.gov.br/bhiss-ws/nfse',
            'version' => '1.00',
            'msgns' => 'http://www.abrasf.org.br/nfse.xsd',
            'soapns' => 'http://ws.bhiss.pbh.gov.br'
        ],
        '3106200' => [
            'municipio' => 'Belo Horizonte',
            'uf' => 'MG',
            'homologacao' => 'https://bhisshomologa.pbh.gov.br/bhiss-ws/nfse',
            'producao' => 'https://bhissdigital.pbh.gov.br/bhiss-ws/nfse',
            'version' => '1.00',
            'msgns' => 'http://www.abrasf.org.br/nfse.xsd',
            'soapns' => 'http://ws.bhiss.pbh.gov.br'
        ]
    ];
    
    /**
     * Constructor
     * @param string $config
     * @param Certificate $cert
     */
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
    
    /**
     * SOAP communication dependency injection
     * @param SoapInterface $soap
     */
    public function loadSoapClass(SoapInterface $soap)
    {
        $this->soap = $soap;
    }
    
    /**
     * Build tag Prestador
     */
    protected function buildPrestadorTag()
    {
        $this->prestador = "<Prestador>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "</Prestador>";
    }

    /**
     * Sign XML passing in content
     * @param string $content
     * @param string $tagname
     * @param string $mark
     * @return string XML signed
     */
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
    
    /**
     * Send message to webservice
     * @param string $message
     * @param string $operation
     * @return string XML response from webservice
     */
    public function send($message, $operation)
    {
        $action = "{$this->wsobj->soapns}/$operation";
        $url = $this->wsobj->homologacao;
        if ($this->environment === 'producao') {
            $url = $this->wsobj->producao;
        }
        $request = $this->createSoapRequest($message, $operation);
        $this->lastRequest = $request;
        
        if (empty($this->soap)) {
            $this->soap = new SoapCurl($this->certificate);
        }
        $msgSize = strlen($request);
        $parameters = [
            "Content-Type: text/xml;charset=UTF-8",
            "SOAPAction: \"$action\"",
            "Content-length: $msgSize"
        ];
        $response = (string) $this->soap->send(
            $operation,
            $url,
            $action,
            $request,
            $parameters
        );
        return $this->extractContentFromResponse($response, $operation);
    }
    
    /**
     * Extract xml response from CDATA outputXML tag
     * @param string $response Return from webservice
     * @return string XML extracted from response
     */
    protected function extractContentFromResponse($response, $operation)
    {
        $dom = new Dom('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($response);
        if (!empty($dom->getElementsByTagName('outputXML')->item(0))) {
            $node = $dom->getElementsByTagName('outputXML')->item(0);
            return $node->textContent;
        }
        return $response;
    }

    /**
     * Build SOAP request
     * @param string $message
     * @param string $operation
     * @return string XML SOAP request
     */
    protected function createSoapRequest($message, $operation)
    {
        $env = "<soapenv:Envelope "
            . "xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" "
            . "xmlns:ws=\"{$this->wsobj->soapns}\">"
            . "<soapenv:Header/>"
            . "<soapenv:Body>"
            . "<ws:{$operation}Request>"
            . "<nfseCabecMsg>"
            . "</nfseCabecMsg>"
            . "<nfseDadosMsg>"
            . "</nfseDadosMsg>"
            . "</ws:{$operation}Request>"
            . "</soapenv:Body>"
            . "</soapenv:Envelope>";
        
        $cabecalho = "<cabecalho xmlns=\"http://www.abrasf.org.br/nfse.xsd\" versao=\"{$this->wsobj->version}\">"
            . "<versaoDados>{$this->wsobj->version}</versaoDados>"
            . "</cabecalho>";
            
        $dom = new Dom('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($env);
        
        $node = $dom->getElementsByTagName('nfseCabecMsg')->item(0);
        $cdata = $dom->createCDATASection($cabecalho);
        $node->appendChild($cdata);
        
        $node = $dom->getElementsByTagName('nfseDadosMsg')->item(0);
        $cdata = $dom->createCDATASection($message);
        $node->appendChild($cdata);
        return $dom->saveXML($dom->documentElement);
    }

    /**
     * Create tag Prestador and insert into RPS xml
     * @param RpsInterface $rps
     * @return string RPS XML (not signed)
     */
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

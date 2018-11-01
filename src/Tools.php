<?php

namespace NFePHP\NFSeNac;

use NFePHP\NFSeNac\Common\Tools as BaseTools;
use NFePHP\NFSeNac\RpsInterface;

class Tools extends BaseTools
{
    const ERRO_EMISSAO = 1;
    const SERVICO_NAO_CONCLUIDO = 2;
    
    public function __construct($config, Certificate $cert)
    {
        parent::__construct($config, $cert);
    }
    
    /**
     * Solicita o cancelamento de NFSe (SINCRONO)
     * @param string $id
     * @param integer $numero
     * @param integer $codigo
     * @return string
     */
    public function cancelarNfse($id, $numero, $codigo = self::ERRO_EMISSAO)
    {
        $response = '';
        $message = "<CancelarNfseEnvio xmlns=\"http://www.abrasf.org.br/nfse.xsd\">"
            . "<Pedido xmlns=\"http://www.abrasf.org.br/nfse.xsd\">"
            . "<InfPedidoCancelamento Id=\"$id\">"
            . "<IdentificacaoNfse>"
            . "<Numero>$numero</Numero>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "<CodigoMunicipio>" . $this->config->cmun . "</CodigoMunicipio>"
            . "</IdentificacaoNfse>"
            . "<CodigoCancelamento>$codigo</CodigoCancelamento>"
            . "</InfPedidoCancelamento>";
        
        return $this->send($message);
    }
    
    /**
     * Consulta Lote RPS (SINCRONO) após envio com recepcionarLoteRps() (ASSINCRONO)
     * complemento do processo de envio assincono.
     * Que deve ser usado quando temos mais de um RPS sendo enviado
     * por vez.
     * @param string $protocolo
     * @return string
     */
    public function consultarLoteRps($protocolo)
    {
        $response = '';
        $message = "<ConsultarLoteRpsEnvio xmlns=\"http://www.abrasf.org.br/nfse.xsd\">"
            . $this->prestador
            . "<Protocolo>$protocolo</Protocolo>"
            . "</ConsultarLoteRpsEnvio>";
        return $this->send($message);
    }
    
    /**
     * Consulta NFSe emitidas em um periodo e por tomador (SINCRONO)
     * @param string $dini
     * @param string $dfim
     * @param string $tomadorCnpj
     * @param string $tomadorCpf
     * @param string $tomadorIM
     * @return string
     */
    public function consultarNfse($dini, $dfim, $tomadorCnpj = null, $tomadorCpf = null, $tomadorIM = null)
    {
        $response = '';
        $message = "<ConsultarNfseEnvio xmlns=\"http://www.abrasf.org.br/nfse.xsd\">"
            . $this->prestador
            . "<PeriodoEmissao>"
            . "<DataInicial>$dini</DataInicial>"
            . "<DataFinal>$dfim</DataFinal>"
            . "</PeriodoEmissao>";
            
            if ($tomadorCnpj || $tomadorCpf) {
                $message .= "<Tomador>"
                . "<CpfCnpj>"
                . !empty($tomadorCnpj) ? "<Cnpj>$tomadorCnpj</Cnpj>" : ""
                . !empty($tomadorCpf) ? "<Cpf>$tomadorCpf</Cpf>" : ""    
                . "</CpfCnpj>"
                . !empty($tomadorIM) ? "<InscricaoMunicipal>$tomadorIM</InscricaoMunicipal>" : ""
                . "</Tomador>";
            }    
            $message .= "</ConsultarNfseEnvio>";
        
        return $this->send($message);
    }
    
    /**
     * Consulta NFSe emitidas por faixa de numeros (SINCRONO)
     * @param integer $nini
     * @param integer $nfim
     * @param integer $pagina
     * @return string
     */
    public function consultarNfsePorFaixa($nini, $nfim, $pagina = 1)
    {
        $response = '';
        $message = "<ConsultarNfseFaixaEnvio xmlns=\"http://www.abrasf.org.br/nfse.xsd\">"
            . $this->prestador
            . "<Faixa>"
            . "<NumeroNfseInicial>$nini</NumeroNfseInicial>"
            . "<NumeroNfseFinal>$nfim</NumeroNfseFinal>"
            . "</Faixa>"
            . "<Pagina>$pagina</Pagina>"
            . "</ConsultarNfseFaixaEnvio>";
        
        return $this->send($message);
    }
    
    /**
     * Consulta NFSe por RPS (SINCRONO)
     * @param integer $numero
     * @param string $serie
     * @param integer $tipo
     * @return string
     */
    public function consultarNfsePorRps($numero, $serie, $tipo)
    {
        $response = '';
        $message = "<ConsultarNfseRpsEnvio xmlns=\"http://www.abrasf.org.br/nfse.xsd\">"
            . "<IdentificacaoRps>"
            . "<Numero>$numero</Numero>"
            . "<Serie>$serie</Serie>"
            . "<Tipo>$tipo</Tipo>"
            . "</IdentificacaoRps>"
            . $this->prestador
            . "</ConsultarNfseRpsEnvio>";
        
        return $this->send($message);
    }
    
    /**
     * Envia LOTE de RPS para emissão de NFSe (ASSINCRONO)
     * @param array $arps Array contendo de 1 a 50 RPS::class
     * @return string
     * @throws \Exception
     */
    public function recepcionarLoteRps($arps)
    {
        $response = '';
        $no_of_rps_in_lot = count($arps);
        if ($no_of_rps_in_lot > 50) {
            throw new \Exception('O limite é de 50 RPS por lote enviado.');
        }
        $message = "<EnviarLoteRpsEnvio xmlns=\"http://www.abrasf.org.br/nfse.xsd\">"
            . "<LoteRps Id=\"lote\" versao=\"1.00\">"
            . "<NumeroLote>1</NumeroLote>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "<QuantidadeRps>$no_of_rps_in_lot</QuantidadeRps>"
            . "<ListaRps>"
            . "</ListaRps>"
            . "</LoteRps>"
            . "</EnviarLoteRpsEnvio>";
        
        return $this->send($message);
    }
    
    /**
     * Solicita a emissão de uma NFSe de forma SINCRONA
     * @param RpsInterface $rps
     * @param string $lote Identificação do lote
     * @return string
     */
    public function gerarNfse(RpsInterface $rps, $lote)
    {
        $response = '';
        $message = "<GerarNfseEnvio xmlns=\"http://www.abrasf.org.br/nfse.xsd\">"
            . "<LoteRps Id=\"Lote2014111893123\" versao=\"1.00\">"
            . "<NumeroLote>$lote</NumeroLote>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "<QuantidadeRps>1</QuantidadeRps>"
            . "<ListaRps>"
            . "</ListaRps>"
            . "</GerarNfseEnvio>";
        
        return $this->send($message);
    }
}

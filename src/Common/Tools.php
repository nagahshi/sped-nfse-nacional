<?php

namespace NFePHP\NFSeNac\Common;

use NFePHP\Common\Certificate;

class Tools
{
    protected $config;
    protected $prestador;
    
    public function __construct($config, Certificate $cert)
    {
        $this->config = json_decode($config);
        $this->buildPrestadorTag();
    }
    
    protected function buildPrestadorTag()
    {
        $this->prestador = "<Prestador>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "</Prestador>";
    }


    public function sign()
    {
        
    }
}

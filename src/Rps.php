<?php

namespace NFePHP\NFSeNac;

use stdClass;
use NFePHP\Common\Certificate;
use NFePHP\NFSeNac\RpsInterface;
use NFePHP\NFSeNac\Common\Factory;
use JsonSchema\Validator as JsonValid;

class Rps implements RpsInterface
{
    protected $std;
    protected $ver;
    protected $jsonschema;

    public function __construct(stdClass $rps = null)
    {
        $this->init($rps);
    }
    
    public function render(stdClass $rps = null)
    {
        $this->init($rps);
        $fac = new Factory($this->std);
        return $fac->render();
    }
    
    private function init(stdClass $rps = null)
    {
        if (!empty($rps)) {
            $this->std = $this->propertiesToLower($rps);
            $ver = str_replace('.', '_', $rps->version);
            $this->jsonschema = realpath("../storage/jsonSchemes/v$ver/rps.schema");
            $this->validInputData($this->std);
        }
    }
    
    /**
     * Change properties names of stdClass to lower case
     * @param stdClass $data
     * @return stdClass
     */
    protected static function propertiesToLower(stdClass $data)
    {
        $properties = get_object_vars($data);
        $clone = new stdClass();
        foreach ($properties as $key => $value) {
            if ($value instanceof stdClass) {
                $value = self::propertiesToLower($value);
            }
            $nk = strtolower($key);
            $clone->{$nk} = $value;
        }
        return $clone;
    }

    /**
     * Validation json data from json Schema
     * @param stdClass $data
     * @return boolean
     * @throws \RuntimeException
     */
    protected function validInputData($data)
    {
        if (!is_file($this->jsonschema)) {
            return true;
        }
        $validator = new JsonValid();
        $validator->check($data, (object)['$ref' => 'file://' . $this->jsonschema]);
        if (!$validator->isValid()) {
            $msg = "";
            foreach ($validator->getErrors() as $error) {
                $msg .= sprintf("[%s] %s\n", $error['property'], $error['message']);
            }
            throw new \InvalidArgumentException($msg);
        }
        return true;
    }

}

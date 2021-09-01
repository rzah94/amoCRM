<?php

namespace app;

require_once 'Request.php';


class Contact
{
    private $req;
    private $referer;
    private $amoRequest = '/api/v4/contacts';

    private $contactDate = [];
    private $custom_fields_values = [];


    public function __construct(Request $req)
    {
        $this->req = $req;
        $this->referer = $req->getReferer();
    }

    public function d($val, $message = null)
    {
        if ($message) {
            echo '<b>' . $message . ':</b>';
        }

        echo '<pre>';
        print_r($val);
        echo '</pre>';
    }

    public function getReferer()
    {
        return $this->referer;
    }

    public function __get($name)
    {
        echo "Получение '$name'\n";

        if ($name == "custom_fields_values") {
            return $this->custom_fields_values;
        }

        if (array_key_exists($name, $this->contactDate)) {
            return $this->contactDate[$name];
        } else {
            throw new \Exception('Undefined property ' . $name . ' referenced.');
        }
    }

    /**
     * Ищет контакт и получает его значения
     */
    public function find($query)
    {
        $param = [
            'query' => $query,
            'limit' => 1,
            'page' => 1
        ];

        $res = $this->req->get($this->amoRequest . '?' . http_build_query($param));

        if (json_decode($res, true)) {
            $json = json_decode($res, true);

            @$contactData = $json['_embedded']['contacts'][0];

            if (isset($contactData)) {

                $this->contactDate = $contactData;

                $this->d($contactData);

                if (isset($contactData['custom_fields_values'])) {
                    $fields = $contactData['custom_fields_values'];

                    foreach ($fields as $field) {

                        $this->custom_fields_values[] = [
                            'field_id' => $field['field_id'],
                            'field_name' => $field['field_name'],
                            'field_type' => $field['field_type'],
                            'values' => $field['values'],
                        ];
                    }
                }
            } else {
                throw new \Exception('Contact was not find');
            }
        }
    }

    /**
     * Добавляет к полю массив вида [field_id, field_name, 'value' = [values]]
     */
    public function addCustomField(array $field)
    {
    }
}

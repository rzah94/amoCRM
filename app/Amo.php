<?php

class Amo
{
    private $req;
    private $referer;

    private $amoRequest;

    public function __construct(Request $req)
    {
        $this->req = $req;

        $this->referer = $req->getReferer();
    }

    public function getReferer()
    {
        return $this->referer;
    }


    /*

            echo '<pre>';
            //var_dump(json_encode($k, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            print_r(json_decode($k, true),);
            echo '</pre>';
    */

    public function convert_ru_tel_num($tel_no = null)
    {
        if (empty($tel_no)) {
            return false;
        }

        $tel_no = urldecode($tel_no);

        $ru_tel_no = preg_replace('/[^0-9.]+/', '', $tel_no);
        if (strlen($ru_tel_no) == 11 && substr($ru_tel_no, 0, 1) == 8) {
            $ru_tel_no = substr_replace($ru_tel_no, '7', 0, 1);
        } elseif (strlen($ru_tel_no) == 10) {
            $ru_tel_no = '7' . $ru_tel_no;
        }
        $ru_tel_no = '+' . $ru_tel_no;
        return $ru_tel_no;
    }

    public function formatPhone($phone)
    {
        return substr($phone, -11);
    }


    public function contacts()
    {
        $this->amoRequest = '/api/v4/contacts';

        return $this;
    }

    public function leads()
    {
        $this->amoRequest = '/api/v4/leads';

        return $this;
    }

    public function users()
    {
        $this->amoRequest = '/api/v4/users';

        return $this;
    }



    public function getIdByName($name)
    {
        $res = $this->req->get($this->amoRequest);
        $json = json_decode($res, true);

        foreach ($json['_embedded']['users'] as $user) {
            if ($user['name'] == $name) {
                return $user['id'];
            }
        }

        return null;
    }

    // получает добавочный номер сотрудника
    public function getEmployeeByNumber($addNumber)
    {
        $res = $this->req->getProstie_Zvonki("/ajax/settings/widgets/category/own_integrations/1/");
        $json = json_decode($res, true);

        $json = $json['widgets']['own_integrations']['installed']['amo_prostiezvonki']['settings']['phones']['value'];

        foreach (json_decode($json, true) as $id => $number) {
            if ($number == $addNumber) {
                return $id;
            }
        }
        return null;
    }


    // Получает из массива контакта телефоны и почты
    public function getDataContacts(array $contacts)
    {
        $result = [];
        foreach ($contacts as $contact) {

            $emails = [];
            $phones = [];

            if ($contact['custom_fields_values']) {

                foreach ($contact['custom_fields_values'] as $fields) {


                    if (trim($fields["field_name"]) == "Email") {

                        foreach ($fields["values"] as $value) {
                            @$emails[] = $value["value"];
                        }
                    }

                    if (trim($fields["field_name"]) == "Телефон") {

                        foreach ($fields["values"] as $value) {
                            @$phones[] = $value["value"];
                        }
                    }
                }
            }

            $result[] =  [
                'id' => $contact['id'],
                'name' => $contact['name'],
                'phone' => $phones,
                'email' => $emails,
            ];
        }

        return $result;
    }

    /*
    проверяет есть ли контактные данные (телефон и почта) в контакте
    Возвращяет true, если пуст, false - не пуст
    */
    public function isContactEmpty(array $contact)
    {
        if ((count($contact["phone"]) == 0) && (count($contact["email"]) == 0)) {
            return true;
        }
        return false;
    }




    public function findContacts($query, $limit = 250, $page = 1, $withoutWords = [], $withoutTags = [])
    {

        $param = [
            'query' => $query,
            'limit' => $limit,
            'page' => $page
        ];

        $res = $this->req->get('/api/v4/contacts?' . http_build_query($param));

        if (json_decode($res, true)) {
            $json = json_decode($res, true);

            $allContacts =  $this->getDataContacts($json['_embedded']['contacts']);

            if ($withoutWords) {

                foreach ($withoutWords as $key => $w) {

                    $wLen = mb_strlen($w);
                    foreach ($allContacts as $key2 => $contact) {

                        if (mb_substr($contact['name'], 0, $wLen) == $w) {
                            unset($allContacts[$key2]);
                        }
                    }
                }
            }

            if ($withoutTags) {
                foreach ($withoutTags as $key => $w) {

                    foreach ($allContacts as $key3 => $contact) {


                        $tags = $this->getAllTags($contact["id"]);

                        foreach ($tags as $tag) {
                            if ($tag["name"] == $w) {
                                unset($allContacts[$key3]);
                            }
                        }
                    }
                }
            }

            return $allContacts;
        }
        return null;
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

    /**
     * Получение всех сделок
     * @param array $params параметры запроса
     * @return array
     */
    public function getAllLeads($params = [])
    {
        $params = http_build_query($params);

        $res = $this->req->get('/api/v4/leads?' . $params);
        $json = json_decode($res, true);

        return $json['_embedded']["leads"];
    }



    /**
     * Получить Id воронки по названию
     * @param string $name название воронки
     * @return int
     */
    public function getPipelinesIdByName($name)
    {
        $res = $this->req->get($this->amoRequest . '/pipelines');

        $json = json_decode($res, true)['_embedded']['pipelines'];

        foreach ($json as $pipeline) {
            if (mb_strtolower($pipeline['name']) == mb_strtolower($name)) {
                return $pipeline['id'];
            }
        }

        return null;
    }


    /**
     * Получить Id статуса по названию
     * @param int id воронки
     * @param string $name название статуса
     * @return int
     */
    public function getStatusIdByName($pipeline_id, $name)
    {
        $res = $this->req->get($this->amoRequest . '/pipelines');

        $json = json_decode($res, true)['_embedded']['pipelines'];

        foreach ($json as $pipeline) {

            // ищем нужную воронку
            if ($pipeline['id'] == $pipeline_id) {

                // ищем нужный статус
                foreach ($pipeline['_embedded']['statuses'] as $status) {
                    if (mb_strtolower($status['name']) == mb_strtolower($name)) {
                        return $status['id'];
                    }
                }
            }
        }

        return null;
    }



    /**
     * Получить все сделки по id статуса
     * @param string $statusId id сделки
     * @return array
     */
    public function getLeadsByIdStatus($statusId)
    {
        $leadssAll = $this->getAllLeads();
        $res = [];

        foreach ($leadssAll as $lead) {
            if ($lead['status_id'] ==  $statusId) {
                $res[] = $lead;
            }
        }

        return $res;
    }

    /**
     * Получение всех полей сущности
     * @return array
     */
    public function getAllFields()
    {
        $res = $this->req->get($this->amoRequest . '/custom_fields');
        $json = json_decode($res, true);

        return $json['_embedded']["custom_fields"];
    }

    /**
     * Получение всех полей (contacts)
     * @return array
     */
    public function getAllContactsField()
    {
        $res = $this->req->get('/api/v4/contacts/custom_fields');
        $json = json_decode($res, true);

        return $json['_embedded']["custom_fields"];
    }


    /**
     * Получение всех контактов
     * @return array
     */
    public function getAllContacts()
    {
        $res = $this->req->get('/api/v4/contacts');
        $json = json_decode($res, true);

        $contacts = [];

        foreach ($json['_embedded']["contacts"] as $contact) {

            $emails = [];
            $phones = [];

            if ($contact['custom_fields_values']) {

                foreach ($contact['custom_fields_values'] as $fields) {

                    if (trim($fields["field_name"]) == "Email") {

                        foreach ($fields["values"] as $value) {
                            @$emails[] = $value["value"];
                        }
                    }

                    if (trim($fields["field_name"]) == "Телефон") {

                        foreach ($fields["values"] as $value) {
                            @$phones[] = $value["value"];
                        }
                    }
                }
            }


            $contacts[] =  [
                'id' => $contact['id'],
                'name' => $contact['name'],
                'phone' => $phones,
                'email' => $emails,
            ];
        }

        return $contacts;
    }


    /**
     * Получение всех контактов по имени
     * @return array
     */
    public function getAllContactsByName(string $contactName)
    {
        $allContacts = $this->getAllContacts();

        $result = [];

        foreach ($allContacts as $contact) {

            // проверка на наличие строки $contactName в имени
            $nameLen = mb_strlen($contactName);

            if (mb_substr($contact['name'], 0, $nameLen) != $contactName) {
                continue;
            }

            // если нет контактных данных (кроме имени), то не добавляем в массив с результатом
            if ((!$contact['email']) && (!$contact['phone'])) {
                continue;
            }

            $result[] = $contact;
        }

        return $result;
    }


    // Получение контакта по ID со всей информацией
    public function getContactByIdAll($id)
    {
        $res = $this->req->get('/api/v4/contacts/' . $id);
        $json = json_decode($res, true);
        return $json;
    }


    // Получение контакта по ID
    public function getContactById($id)
    {
        $allContacts = $this->getAllContacts();

        foreach ($allContacts as $contact) {
            if ($contact['id'] == $id) {
                return $contact;
            }
        }
        return null;
    }


    // сравнивает поля контактов из Амо. Возвращает массив с ключом ID, значениями массивами с  'name', 'phone', 'email' (в зависимости от совпадения)
    public function comparisonContactsBySubj($name, array $emails = [], array $phones = [], $id = null, $withoutWords = [])
    {
        if ($id) {
            $this->addContactPhone($id, $phones);
        }

        // ищем контакты с максимальным совпадением
        $allContacts = [];

        if ($emails) {
            foreach ($emails as $email) {

                $arrs1 = $this->findContacts(mb_strtolower($email), 3, 1, $withoutWords);

                if ($arrs1) {
                    foreach ($arrs1 as $arr) {
                        $allContacts[] = $arr;
                    }
                }
            }
        }

        if ($phones) {
            foreach ($phones as $phone) {
                $arrs2  =  $this->findContacts($this->formatPhone($phone), 3, 1, $withoutWords);

                if ($arrs2) {
                    foreach ($arrs2 as $arr) {
                        $allContacts[] = $arr;
                    }
                }
            }
        }

        foreach ($allContacts as $key1 => $allContact) {

            foreach ($allContact["phone"] as $key2 => $phone) {
                $allContacts[$key1]["phone"][$key2] = $this->formatPhone(trim($allContacts[$key1]["phone"][$key2]));
            }

            foreach ($allContact["email"] as $key3 => $email) {
                $allContacts[$key1]["email"][$key3] = mb_strtolower(trim($allContacts[$key1]["email"][$key3]));
            }
        }


        // d($allContacts, "allContacts");
        //$allContacts = $this->getAllContacts();
        $res = [];

        foreach ($allContacts as $contact) {

            $data = '';

            // если id совпадает с контактом, который нашелся, то пропустить контакт
            if ($id) {
                if (trim($contact['id']) == trim($id)) {
                    continue;
                }
            }


            if ($name) {

                if (trim($contact['name']) == trim($name)) {
                    $data .= 'name|';
                }

                // если имя с "del_", то сравниваем без "del_"
                if (mb_substr($name, 0, 4) == "del_") {
                    $nameWithoutDel = str_replace("del_", "", $name);

                    if (trim($contact['name']) == trim($nameWithoutDel)) {
                        $data .= 'name|';
                    }
                }
            }

            if ($emails) {

                foreach ($emails as $email) {

                    if (in_array(trim($email), $contact['email'])) {
                        $data .= 'email|';
                    }
                }
            }

            if ($phones) {

                foreach ($phones as $phone) {

                    if (in_array(trim($phone), $contact['phone'])) {
                        $data .= 'phone|';
                    }
                }

                //    d($contact['phone'], "contact['phone']");
            }

            if ($data != '') {
                $data = trim($data, '|');
                $data =  explode('|', $data);
                $res[$contact['id']] = array_unique($data);
            }
        }

        // найдены совпадения
        if (count($res) > 0) {

            $max_comp = 0;
            $compareResult = [];  // данные сделки с совпадениями

            // ищем из найденных наибольшее количество совпадения контактных данных
            // $compareResult - искомый массив
            foreach ($res as $id => $compare) {

                if ($max_comp < count($compare)) {
                    $compareResult['id'] = $id;
                    $compareResult['data'] = $compare;

                    $max_comp = count($compare);
                }
            }

            // 2+ совпадений - возвращяем результат
            if (count($compareResult['data']) >= 1) {
                if ((in_array('phone', $compareResult['data'])) || (in_array('email', $compareResult['data']))) {
                    return $compareResult;
                }
            } else {
                return null;
            }
        }
    }


    /**
     * Получение Id поля по его названию
     * @param string $name название поля
     * @return int
     */
    public function getFieldIdByName($name)
    {
        $allFields = $this->getAllLeadsField();
        $fieldId = null;

        foreach ($allFields as $field) {

            if ($field['name'] == $name) {
                $fieldId = $field['id'];
            }
        }
        return $fieldId;
    }


    /**
     * Получение значения поля по имени у определенной сделки
     * @param array $lead данные о сделке
     * @param string $fieldName название поля
     * @return string
     */
    public function getFieldValueByFieldName($lead, $fieldName)
    {
        foreach ($lead['custom_fields_values'] as $field) {

            if ($field['field_name'] == $fieldName) {
                return $field['values'];
            }
        }
    }


    /**
     * Получение unixtime, его сравнение с датой в формате Y-m-d
     * @param int $unixtime UnixTime
     * @param string $compDate сравниваемая строка
     * @return bool
     */
    public function compareDate($unixtime, $compDate)
    {
        $date = new DateTime();
        $date->setTimestamp($unixtime);

        if ($date->format('Y-m-d') == $compDate) {
            return true;
        }

        return false;
    }



    /**
     * Изменение значения поля по id у определенной сущности
     * @param int $id Id сущности
     * @param int $fieldId Id поля
     * @param string $value значение
     * @return array
     */
    public function changeField($id, $fieldId, $value)
    {
        $data = [
            'custom_fields_values' => [
                0 => [
                    'field_id' => $fieldId,
                    'values' => [
                        0 => [
                            'value' => $value,
                        ],
                    ],
                ],
            ],
        ];

        $request = $this->amoRequest .'/' . $id;

        $res = $this->req->patch($request, $data);
        return json_decode($res, true);
    }

    /**
     * Добавление контактов
     * @param string $first_name имя контакта
     * @param array $email email контакта
     * @param array $phone телефон контакта
     * @return array
     */
    public function createContact($first_name, $email, $phone)
    {

        $formatPhones = [];
        $formatEmails = [];

        // форматируем переданные данные
        $phones = array_values(array_unique($phone));
        $emails = array_values(array_unique($email));

        foreach ($phones as $key => $phone) {
            $formatPhones[] = ['value' => $phone];
        }

        foreach ($emails as $key => $email) {
            $formatEmails[] = ['value' => $email];
        }

        if ($formatPhones && $formatEmails) {

            $data = [
                [
                    'first_name' => $first_name,
                    'custom_fields_values' => [
                        [
                            "field_id" => $this->getContactIdFieldByName("Email"),
                            'values' => $formatEmails,
                        ],
                        [
                            "field_id" => $this->getContactIdFieldByName("Телефон"),
                            'values' => $formatPhones,
                        ],
                    ]
                ],
            ];
        } elseif ($formatPhones) {
            $data = [
                [
                    'first_name' => $first_name,
                    'custom_fields_values' => [
                        [
                            "field_id" => $this->getContactIdFieldByName("Телефон"),
                            'values' => $formatPhones,
                        ],
                    ]
                ],
            ];
        } elseif ($formatEmails) {

            $data = [
                [
                    'first_name' => $first_name,
                    'custom_fields_values' => [
                        [
                            "field_id" => $this->getContactIdFieldByName("Email"),
                            'values' => $formatEmails,
                        ],
                    ]
                ],
            ];
        } else {

            $data = [
                [
                    'first_name' => $first_name
                ],
            ];
        }

        $res = $this->req->post('/api/v4/contacts', $data);
        return json_decode($res, true)['_embedded']['contacts'][0]['id'];
    }


    /*
     Редактирование phone контакта
     если передается пустой массив, то контакты в сделке будут удалены 
    */
    public function editContactPhone($id, array $phones)
    {
        $formatPhones = [];

        // форматируем переданные данные
        $phones = array_values(array_unique($phones));

        foreach ($phones as $key => $phone) {
            $formatPhones[] = ['value' => $phone];
        }

        $data = [
            'custom_fields_values' => [
                0 => [
                    'field_id' => $this->getContactIdFieldByName("Телефон"),
                    'values' => $formatPhones,
                ],
            ],
        ];

        $res = $this->req->patch('/api/v4/contacts/' . $id, $data);
        return $res;
    }


    /*
    Добавление телефона к телефонам контакта
    */
    public function addContactPhone($id, array $phones)
    {

        if ($phones) {

            $contact = $this->getContactByIdAll($id);

            // получаем все телефонные номера контакта
            $contactPhone = $this->getDataContacts([$contact])[0]["phone"];

            //d($contactPhone, "contactPhone");

            // получаем все телефонные номера, которые должны быть у контакта
            $contactPhones = array_merge($phones, $contactPhone);

            // форматируем номера
            $res = [];

            foreach ($contactPhones as $contactPhone) {
                $res[] = $this->convert_ru_tel_num($contactPhone);
            }

            $res = array_unique($res);

            // добавляем все номера
            return $this->editContactPhone($id, $res);
        }
    }



    /*
     Редактирование email контакта
     если передается пустой массив, то контакты в сделке будут удалены 
    */
    public function editContactEmail($id, array $emails)
    {
        $formatEmails = [];

        // форматируем переданные данные
        $emails = array_values(array_unique($emails));

        foreach ($emails as $key => $email) {

            $formatEmails[] = ['value' => $email];
        }

        $data = [
            'custom_fields_values' => [
                0 => [
                    'field_id' => $this->getContactIdFieldByName("Email"),
                    'values' => $formatEmails,
                ],
            ],
        ];

        $res = $this->req->patch('/api/v4/contacts/' . $id, $data);
        return $res;
    }


    /*
    Добавление email к почтам контакта
    */
    public function addContactEmail($id, array $emails)
    {

        $contact = $this->getContactByIdAll($id);

        // получаем все почты контакта
        // $contactEmail = $this->getContactByIdAll($id)["email"];
        $contactEmail = $this->getDataContacts([$contact])[0]["email"];

        // получаем все почты, которые должны быть у контакта
        $contactEmails = array_unique(array_merge($emails, $contactEmail));

        // добавились новые почты
        if (count($contactEmails) != count($contactEmail)) {

            // добавляем все почты
            return $this->editContactEmail($id, $contactEmails);
        }
    }



    /*
     Редактирование имени контакта
    */
    public function editContactName(int $id, string $name)
    {
        $data = [
            "first_name" => $name
        ];

        $res = $this->req->patch('/api/v4/contacts/' . (int)$id, $data);
        return json_decode($res);
    }

    /*
    Добавление имени для контакта, если его нет
    */
    public function addContactName(int $id, string $name)
    {
        // получаем имя контакта
        $contactName = $this->getContactByIdAll($id)["name"];

        // если имя пустое - добавляем имя
        if ($contactName == null) {
            return $this->editContactName($id, str_replace("del_", "", $name));
        }
    }


    /**
     * Создать сделку и контакт
     * @param string $leadName название сделки
     * @param object $contact данные о контакте
     * @return array
     */
    public function createLead($leadName, $contactId, $price, $status_id)
    {

        $data = [
            [
                "name" => $leadName,
                "price" => (int)$price,
                "status_id" => $status_id,
                "_embedded" => [
                    "contacts" => [
                        0 =>
                        [
                            "id" => (int)$contactId,
                        ],

                    ]
                ]
            ]
        ];

        $res = $this->req->post('/api/v4/leads/complex', $data);
        return json_decode($res)[0];
    }


    /**
     * Создание примечания в контакте
     * @param int $id ID сущности
     * @param string $text Текст примечания
     */
    public function createNote($id, $text)
    {
        $data = [
            [
                "entity_id" => $id,
                "note_type" => "common",
                "params" => ["text" => $text]
            ],
        ];

        $res = $this->req->post($this->amoRequest . '/notes', $data);
        return json_decode($res)->_embedded->notes;
    }


    /**
     * Получения примечания сущности
     * @param int $id ID сущности
     */
    public function getNote($id)
    {
        return $this->req->get($this->amoRequest  . '/' . $id . '/notes');
    }

    /**
     * Получения примечания сделки
     */
    public function getNoteInLead($lead_id)
    {
        $res = $this->req->get('/api/v4/leads/' . $lead_id . '/notes');
        return $res;
    }


    // например, "Должность", "Телефон", "Email"
    public function getContactIdFieldByName($name = null)
    {
        foreach ($this->getAllContactsField() as $feld) {

            if ($name == $feld['name']) {
                return $feld['id'];
            }
        }
    }


    /**
     * Возвращяет массив с тегами контакта
     * return array
     */
    public function getAllTags($id)
    {

        if ($this->amoRequest = '/api/v4/leads') {
            return $this->getById($id)["_embedded"]["tags"];
        } else {

            return $this->getContactByIdAll($id)["_embedded"]["tags"];
        }
    }



    // Получение сущности по ID со всей информацией
    public function getById($id)
    {
        $res = $this->req->get($this->amoRequest .'/'. $id);
        $json = json_decode($res, true);
        return $json;
    }



    public function getLeadsbyId($contact_id)
    {
        $res = $this->req->get($this->amoRequest .'/'. $contact_id . '?with=leads');
        $json = json_decode($res, true)["_embedded"]["leads"];

        $res = [];
        if ($json) {
            foreach ($json as $lead) {
                $res[] = $lead['id'];
            }
            return $res;
        }
        return null;
    }





    /**
     * @param int $pipeline_id id воронки
     * @param int $status_id ID статуса
     * @param int $responsible_user_id ответственный за сделку
     * @param int $contactId id контакта
     */
    public function create($pipeline_id, $status_id, $responsible_user_id, $contactId, $custom_fields_values)
    {

        /*$d = ['custom_fields_values' => [
            0 => [
                'field_id' => 3,
                'values' => [
                    0 => [
                        'value' => 'Значение поля',
                    ],
                ],
            ],
            1 => [
                'field_id' => 103,
                'values' => [
                    0 => [
                        'value' => '1.5',
                    ],
                ],
            ],
        ],];
*/

        $custom_field = [];
        $custom_field = [];
        $data_post = [];

        if ($custom_fields_values) {
            foreach ($custom_fields_values as $fieldName => $fieldId) {


                if (($fieldName == "to_date") || ($fieldName == "from_date")) {

                    $custom_field[] = [
                        'field_id' => $fieldId,
                        'values' => [
                            ['value' => strtotime($_POST[$fieldName])]
                        ]
                    ];
                } else if (($fieldName == "hotel") || ($fieldName == "food")) {



                    foreach ($_POST[$fieldName] as $post_field) {
                        $data_post[] = ['value' => $post_field];
                    }


                    $custom_field[] = [
                        'field_id' => $fieldId,
                        'values' =>

                        $data_post


                    ];

                    $data_post = [];
                } else {


                    $custom_field[] = [
                        'field_id' => $fieldId,
                        'values' => [
                            ['value' => $_POST[$fieldName]]
                        ]
                    ];
                }
            }
        }

        $custom_fields['custom_fields_values'] = $custom_field;


        $data = [
            [
                "pipeline_id" => $pipeline_id,
                "status_id" => $status_id,
                "responsible_user_id" => $responsible_user_id,
                "_embedded" => [
                    "contacts" => [
                        0 =>
                        [
                            "id" => (int)$contactId,
                        ],

                    ]
                ],

                "custom_fields_values" => $custom_fields["custom_fields_values"]
            ]
        ];

        // $this->d($custom_fields_values, "custom_fields_values");

        // $this->d($custom_fields, 'result');
        //$this->d($data, 'data');

        $json = json_decode($this->req->post($this->amoRequest, $data), true);
        return $json["_embedded"]["leads"]["0"]["id"];
    }


    public function addTag($id, $tagName)
    {

        // получаем все теги этого контакта
        $tags = $this->getAllTags($id);

        foreach ($tags as $tag) {
            // такой тег уже есть - новый не добавляем
            if (trim($tag["name"]) == trim($tagName)) {
                return null;
            }
        }

        $tags[] = ["name" => $tagName];

        $data = [
            [
                "id" => $id,
                "_embedded" => ["tags" =>  $tags]
            ]
        ];

        return $this->req->patch($this->amoRequest, $data);
    }


    public function deleteTag($id, $tagName)
    {

        // получаем все теги этого контакта
        $tags = $this->getAllTags($id);
        $tagsCount = count($tags);

        foreach ($tags as $key => $tag) {
            // находим нужный тег и удаляем его
            if (trim($tag["name"]) == trim($tagName)) {
                unset($tags[$key]);
            }
        }

        // если размер массива не изменился - делаем return
        if ($tagsCount == count($tags)) {
            return null;
        }


        $data = [
            [
                "id" => $id,
                "_embedded" => ["tags" =>  $tags]
            ]
        ];

        return $this->req->patch($this->amoRequest, $data);
    }
}

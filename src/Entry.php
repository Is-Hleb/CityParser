<?php

namespace IsHleb\CityParser;

use GeoNames\Client as GeoNamesClient;

class Entry
{
    public const COUNTRIES = [
        "RU","UA","BY","KZ","AZ","AM","KG","UZ","TJ","MD"
    ];

    private Db $db;
    private GeoNamesClient $client;
    private array $logins = [
        'tomas_lipton',
        'scarlordx1',
        'tempuser1',
        'creaky',
        'creak',
        'asdfgh1',
        'tempuser2',
        'reqqs'
    ];
    private string $activeUser;
    private int $clientIndex;

    public function translit($st, $rotate = true)
    {
        $letters = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v',
            'г' => 'g', 'д' => 'd', 'е' => 'e',
            'е' => 'e', 'ж' => 'zh', 'з' => 'z',
            'и' => 'i', 'й' => 'y', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r',
            'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ь' => '\'', 'й' => 'y', 'ъ' => '\'',
            'е' => 'e', 'ю' => 'yu', 'я' => 'ya',
    
            'А' => 'A', 'Б' => 'B', 'В' => 'V',
            'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
            'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z',
            'И' => 'I', 'Й' => 'Y', 'К' => 'K',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R',
            'С' => 'S', 'Т' => 'T', 'У' => 'U',
            'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch',
            'Ь' => '\'', 'Ы' => 'Y', 'Ъ' => '\'',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        );
        $letters = $rotate ? array_flip($letters) : $letters;
        $st = strtr($st, $letters);
        return $st;
    }

    public function __construct()
    {
        $this->activeUser = $this->logins[0];
        $this->clientIndex = 1;
        $this->client = new GeoNamesClient($this->activeUser);
        $this->db = new Db();
    }

    public function nextClient()
    {
        if (!isset($this->logins[$this->clientIndex])) {
            return false;
        }

        $this->activeUser = $this->logins[$this->clientIndex ++];
        $this->client = new GeoNamesClient($this->activeUser);
        return true;
    }

    public function processCountries()
    {
        foreach (self::COUNTRIES as $code) {
            [$country] = $this->client->countryInfo([
                'country' => $code,
                'lang' => 'ru'
            ]);
            
            $countryId = $this->db->insertCountry($country->geonameId, $country->countryName, $code);

            $regions = [];
            $data = [];
            $index = 0;
            
            do {
                $regions = array_merge($regions, $data);

                $data = $this->client->search([
                    'country' => $code,
                    'startRow' => $index,
                    'maxRows' => 1000,
                    'lang' => 'ru',
                    'q' => [
                        'fcode' => "ADM1",
                    ]
                ]);
                $index += sizeOf($data);
            } while (!empty($data));
            
            foreach ($regions as $region) {
                if (preg_match('/[А-Яа-яЁё]/u', $region->name)) {
                    $this->db->insertState($region->geonameId, $region->name, $countryId);
                    continue;
                }

                $info = $this->client->get([
                    'geonameId' => $region->geonameId,
                    'lang' => 'ru'
                ]);

                foreach ($info->alternateNames as $obj) {
                    if (preg_match('/[А-Яа-яЁё]/u', $obj->name)) {
                        $this->db->insertState($region->geonameId, $obj->name, $countryId);
                        break;
                    }
                }
            }
        }
    }

    public function findLatin($names)
    {
        foreach ($names as $name) {
            if (preg_match('/^[а-яё0-9]+$/iu', $name) && !strchr($name, 'æ')) {
                return $name;
            }
        }
        return false;
    }

    public function processCities()
    {
        foreach (self::COUNTRIES as $code) {
            $country = $this->db->getCountryUsingCode($code);
            $regions = $this->db->getAllCountryStates($country['id']);

            foreach ($regions as $region) {
                $info = $this->client->get([
                    'country' => $code,
                    'geonameId' => $region['geonameId']
                ]);
                // var_dump($info);
                // die();
                $cities = $this->db->getAllCitiesUsingState($code, $info->adminCode1);
                
                foreach ($cities as $city) {
                    if ($city['alternatenames']) {
                        $names = explode(',', $city['alternatenames']);
                        $name = $this->findLatin($names);
                    } else {
                        $name = $city['name'];
                    }
                    
                    if (!$name) {
                        continue;
                    }
                
                    $this->db->insertCity(
                        $country['id'],
                        $region['id'],
                        $name,
                        $city['population'],
                        $city['geonameid']
                    );
                }
            }
        }
    }

    public function processRayonsFromDb() 
    {
        $cities = $this->db->getAllCities();
        $info = 0;
        foreach($cities as $originalCity) {
            $city = $this->db->getCityByGeonameId($originalCity['geonameId']);
            $code = $city['country code'] . '.' . $city['admin1 code'];
            $rayons = $this->db->getAllRayons($code);
            echo "CURRENT CITY: " . $info++ . "\n";
            foreach($rayons as $rayon) {
                $name = $rayon['asciiname'];
                $name = $this->translit($name);

                $this->db->insertRayon(
                    $originalCity['id_country'],
                    $originalCity['id_state'],
                    $originalCity['id'],
                    $name
                );
            }
        }
    }

    public function processRayons()
    {
        $cities = $this->db->getAllCities();
        $infoIndex = 0;
        foreach ($cities as $city) {
            $geonameId = $city['geonameId'];
            $Dbcity = $this->db->getCityByGeonameId($geonameId);
            echo "INFO: " . $infoIndex++ . "\n";
            
            $rayons = [];
            $index = 0;

//            if($infoIndex < 1865) continue;
            do {
                try {
                    $data = $this->client->search([
                        'startRow' => $index,
                        'rowsCount' => 1000,
                        'country' => $Dbcity['country code'],
                        'adminCode1' => $Dbcity['admin1 code'],
                        'featureCode' => 'PPLX',
                        'lang' => 'ru',
                    ]);
                    $index += sizeOf($data);
                } catch (\Exception $e) {
                    while ($this->nextClient()) {
                        try {
                            $data = $this->client->search([
                                'startRow' => $index,
                                'rowsCount' => 1000,
                                'country' => $Dbcity['country code'],
                                'adminCode1' => $Dbcity['admin1 code'],
                                'featureCode' => 'PPLX',
                                'lang' => 'ru',
                            ]);
                            $index += sizeOf($data);
                            echo "Sended\n";
                            break;
                        } catch (\Exception $e) {
                            echo $e->getMessage() . "\n";
                        }
                    }
                }
                $rayons = array_merge($rayons, $data);
                echo "DATA SIZE: " . sizeof($data) . "\n";
                echo "INDEX: " . $index . "\n\n\n";
            } while (!empty($data));

            foreach ($rayons as $rayon) {
                $this->db->insertRayon(
                    $city['id_country'],
                    $city['id_state'],
                    $city['id'],
                    $rayon->name
                );
            }
        }
    }

    

    public function updateCities()
    {
        $cities = $this->db->getAllCities();
        $index = 0;
        foreach ($cities as $city) {
            $geonameId = $city['geonameId'];
            $id = $city['id'];
            echo $index++ . "\n";
            if ($index < 4790) {
                continue;
            }
            try {
                $info = $this->client->get([
                    'geonameId' => $geonameId,
                    'lang' => 'ru'
                ]);
            } catch (\Exception $e) {
                while ($this->nextClient()) {
                    try {
                        $info = $this->client->get([
                            'geonameId' => $geonameId,
                            'lang' => 'ru'
                        ]);
                        echo "Sended\n";
                        break;
                    } catch (\Exception $e) {
                        echo $e->getMessage() . "\n";
                    }
                }
            }
            $name = $info->name;
            if ($this->findLatin([$name])) {
                $this->db->updateCityName($id, $name);
            }
        }
    }

    public function __invoke()
    {
        // $this->processCountries();

        // $this->processCities();
        
        // $this->processRayons();
        
        $this->processRayonsFromDb();

        // $city = $this->db->getAllCities();
        // $city = $this->db->getCityByGeonameId($city[2]['geonameId']);
        
        // $ans = $this->client->search([
        //     // 'startRow' => 0,
        //     // 'rowsCount' => 1000,
        //     'country' => $city['country code'],
        //     'adminCode1' => $city['admin1 code'],
        //     'adminCode2' => $city['geonameid'],
        //     'featureClass' => 'A',
        //     'lang' => 'ru',
        // ]);
        // var_dump($ans);
        // foreach($ans as &$item) {
        //     $item = $item->name;
        // }
        // var_dump($city);
        // var_dump(
        //    $ans
        // );

        // $this->updateCities();
        
        // $cities = $this->db->getAllCitiesUsingState("RU", 48);

        // foreach($cities as $city) {
        //     if(str_contains($city['alternatenames'], "Mos")) {
        //         var_dump($city);
        //     }
        // }
        
        // for ($i=0; $i < 5; $i++) {
        //     $this->nextClient();
        // }
        // var_dump(
        //     $this->client->children([
        //         'geonameId' => 551487,
        //         'lang' => 'ru',
        //         'q' => [
        //             'fcode' => 'PPLX'
        //         ]
        //     ])
        // );

        // $info = $this->client->get([
        //     'geonameId' => 620134
        // ]);
        // // var_dump($info);
        // $cities = $this->client->search([
        //     'country' => 'BY',
        //     'q' => [
        //         'adminCode1' => '07',
        //         'fcode' => 'PPL'
        //     ],
        //     'lang' => 'ru'
        // ]);
        
    
        // [$country] = $this->client->countryInfo([
        //     'country' => 'MD'
        // ]);

        // $data = $this->client->search([
        //     'country' => 'MD',
        //     'startRow' => 0,
        //     'maxRows' => 1000,
        //     'lang' => 'ru',
        //     'q' => [
        //         'fcode' => 'ADM1',
        //     ]
        // ]);
        // var_dump($data);

        // // var_dump($country);
    }
}

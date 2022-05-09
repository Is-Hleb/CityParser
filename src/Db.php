<?php

namespace IsHleb\CityParser;

use PDO;


class Db
{
    private PDO $pdo;
    
    public function __construct()
    {
        $dsn = "mysql:unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock;dbname=countries;user=root;password=root";
        $this->pdo = new PDO($dsn, 'root', 'root', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    public function insertCountry(string $geoNameId, string $name, $code) : int
    {
        $sql = "INSERT INTO `adres_country` (`name`, `geonameId`, `code`) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$name, $geoNameId, $code]);
        return $this->pdo->lastInsertId();
    }

    public function insertState(string $geonameId, string $name, string $id_country)
    {
        $sql = "INSERT INTO `adres_state` (`name`, `geonameId`, `id_country`) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$name, $geonameId, $id_country]);
    }

    public function getAllCitiesUsingState($code, $adminCode) 
    {
        $sql = "SELECT * FROM `cities` WHERE `country code` = '$code' AND `admin1 code` = '$adminCode' AND `population` > 0";
        $query = $this->pdo->prepare($sql);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertCity($county_id, $state_id, $name, $population, $geonameId) {
        $sql = "INSERT INTO `adres_city` (`id_country`, `id_state`, `name`, `quantity_people`, `geonameId`) 
                VALUES ($county_id, $state_id, \"$name\", '$population', $geonameId)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
    }

    public function insertRayon($country_id, $state_id, $city_id, $name) {
        $sql = "INSERT INTO `adres_suburb` (`name`, `id_country`, `id_state`, `id_city`) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$name, $country_id, $state_id, $city_id]);
    }

    public function updateCityName(int $city_id, string $name) 
    {
        $sql = "UPDATE `adres_city` SET `name` = \"$name\" WHERE `adres_city`.`id` = $city_id";
        $query = $this->pdo->prepare($sql);
        $query->execute();
    }

    public function getAllRayons(string $code) 
    {
        $sql = "SELECT * FROM `rayons` WHERE `code` LIKE '%$code%'";
        $query = $this->pdo->prepare($sql);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCityByGeonameId($id) {
        $sql = "SELECT * FROM `cities` WHERE `geonameid` = $id";
        $query = $this->pdo->prepare($sql);
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllCities() {
        $sql = "SELECT * FROM `adres_city`";
        $query = $this->pdo->prepare($sql);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCountryUsingCode($code) {
        $sql = "SELECT * FROM `adres_country` WHERE `code` = '$code'";
        $query = $this->pdo->prepare($sql);
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllCountryStates($country_id) {

        $sql = "SELECT * FROM `adres_state` WHERE `id_country` = $country_id";
        $query = $this->pdo->prepare($sql);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
}

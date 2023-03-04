<?php

namespace App\Util;

class Sunat
{
  private $urlRUC = "https://api.apis.net.pe/v1/ruc?numero=";
  private $urlDNI = "https://api.apis.net.pe/v1/dni?numero=";
  private $token = "apis-token-1.aTSI1U7KEuT-6bbbCguH-4Y8TI6KS73N";
  private $refererRUC = "http://apis.net.pe/api-ruc";
  private $refererDNI = "https://apis.net.pe/consulta-dni-api";

  public function fetchAgentByRUC($ruc)
  {
    return $this->fetchSunat($this->urlRUC, $ruc, $this->refererRUC);
  }

  public function fetchAgentByDNI($dni)
  {
    return $this->fetchSunat($this->urlDNI, $dni, $this->refererDNI);
  }

  public function fetchSunat($url, $parameter, $referer)
  {
    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => $url . $parameter,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        'Referer: ' . $referer,
        'Authorization: Bearer ' . $this->token
    ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response);
  }
}


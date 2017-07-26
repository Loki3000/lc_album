<?php
/*
* @package LabCMS
* @copyright (c) 2006 Alexander Levin aka Loki (loki_angel@mail.ru)
* @support http://labcms.ru
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/
namespace LCM\lc_album\Service;

abstract class Service
{
    abstract public function getById($id);
    
    public function filterValues(&$data)
    {
        foreach ($data as $key => $value) {
            if (!in_array($key, $this->allowedKeys)) {
                unset($data[$key]);
            }
        }
        return $data;
    }
}

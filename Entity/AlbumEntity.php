<?php
/*
* @package LabCMS
* @copyright (c) 2006 Alexander Levin aka Loki (loki_angel@mail.ru)
* @support http://labcms.ru
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/
namespace LCM\lc_album\Entity;

class AlbumEntity extends \LC\Entity
{
    public function setLogo($file)
    {
        $this->logo_file=$file;
    }
}

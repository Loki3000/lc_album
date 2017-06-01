<?php
/*
* @package LabCMS
* @copyright (c) 2006 Alexander Levin aka Loki (loki_angel@mail.ru)
* @support http://labcms.ru
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/
namespace LCM\lc_album\View;

class ImageView extends \LC\Entity
{
    public $files=array();
    public $album;

    public function setFiles($files)
    {
        $this->files=$files;
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function setAlbum($album)
    {
        $this->album=$album;
    }

    public function getAlbum()
    {
        return $this->album;
    }
}
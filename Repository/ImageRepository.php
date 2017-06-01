<?php
/*
* @package LabCMS
* @copyright (c) 2006 Alexander Levin aka Loki (loki_angel@mail.ru)
* @support http://labcms.ru
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

namespace LCM\lc_album\Repository;

class ImageRepository extends Repository
{
    protected $table='lc_album_images';

    protected $allowedFields=array(
        'album_id', 'name', 'describe', 'filename', 'pos',
        'updated', 'total_votes', 'vote_result', 'rating'
    );

    public function __construct(\LC\ORM $orm, \LC\Cacher $cacher)
    {
        parent::__construct($orm, $cacher);
    }

    public function getByAlbum($album, $page, $atPage, $count=true)
    {
        $key=$this->getCacheKey().$page.'_'.$atPage.'_'.$count;
        if (false === ($data=$this->cacher->get($key))) {
            $table=$this->orm->table($this->table)->where('album_id', $album->id);

            if ($count) $data['totalCount']=$table->count('*');
            if ($atPage) $table->paged($atPage, $page);

            $data['collection']=$table->orderBy('pos')->fetchAll();

            $this->cacher->set($data, $key, array($this->getCacheKeyUpdate()), $this->getCacheTime());
        }
        return $data;
    }

    public function getPrevious($image)
    {
        $item=$this->orm->table($this->table)
            ->where('album_id', $image->album_id)
            ->where('pos < :pos', [':pos'=>$image->pos])
            ->orderBy('pos', 'DESC')
            ->limit(1)
            ->fetch();
        return $item;
    }

    public function getNext($image)
    {
        $item=$this->orm->table($this->table)
            ->where('album_id', $image->album_id)
            ->where('pos > :pos', [':pos'=>$image->pos])
            ->orderBy('pos')
            ->limit(1)
            ->fetch();
        return $item;
    }

    public function getMaxPos($image)
    {
        return $this->orm->table($this->table)
            ->where('album_id', $image->album_id)
            ->max('pos');
    }
}
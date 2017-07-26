<?php
/*
* @package LabCMS
* @copyright (c) 2006 Alexander Levin aka Loki (loki_angel@mail.ru)
* @support http://labcms.ru
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/
namespace LCM\lc_album\Repository;

abstract class Repository
{
    protected $orm;
    protected $cacher;
    protected $entity;

    protected $cache_time=604800;//60*60*24*7

    protected $table;

    protected $allowedFields=array();

    public function __construct(\LC\ORM $orm, \LC\Cacher $cacher, \LC\Entity $entity)
    {
        $this->orm=$orm;
        $this->cacher=$cacher;
        $this->entity=$entity;
    }

    public function getById($id)
    {
        $key=$this->getCacheKey().$id;
        $data=null;

        if (false === ($data=$this->cacher->get($key))) {
            $data=$this->orm->table($this->table, $id);
            $data=$this->prepareResult($data);
            $this->cacher->set($data, $key, array($this->getCacheKeyUpdate()), $this->getCacheTime());
        }
        
        return $data;
    }

    public function getByArrayId($ids)
    {
        $key=$this->getCacheKey().implode('_', $ids);

        if (false === ($data=$this->cacher->get($key))) {
            $table=$this->orm->table($this->table);

            $data=$table->where('id', $ids)->fetchAll();
            $data=$this->prepareResults($data);
            $this->cacher->set($data, $key, array($this->getCacheKeyUpdate()), $this->getCacheTime());
        }
        
        return $data;
    }

    public function delete($item)
    {
        return $this->deleteByArrayId(array($item->id));
    }

    public function deleteByArrayId($ids)
    {
        $result=$this->orm->table($this->table)->where('id', $ids)->delete();
        foreach ($ids as $id) {
            $this->cacher->remove($this->getCacheKey().$id);
        }
        $this->cacher->clean('MATCH_TAG', array($this->getCacheKeyUpdate()));
        return $result;
    }

    protected function getCacheTime()
    {
        return $this->cache_time;
    }
    protected function getCacheKey($table=null)
    {
        if (is_null($table)) {
            $table=$this->table;
        }
        return $table.'_';
    }

    protected function getCacheKeyUpdate()
    {
        return $this->getCacheKey().'update';
    }

    public function save($entity)
    {
        if ($entity->id) {
            $item=$this->orm->createRow($this->table);
            $item->id=$entity->id;
            $item->setClean();
            $item->setData($this->getFilteredFields($entity->getArray()));
        } else {
            var_dump($entity->getArray());
            exit;
            $item=$this->orm->createRow($this->table, $this->getFilteredFields($entity->getArray()));
        }
        $item->save();
        if (!$entity->id) {
            $entity->id=$item->id;
        } else {
            $this->cacher->remove($this->getCacheKey().$item->id);
        }
        $this->cacher->clean('MATCH_TAG', array($this->getCacheKeyUpdate()));
        return $entity;
    }
    
    public function create($data)
    {
        return new $this->entity($data);
    }

    protected function prepareResult($item)
    {
        if (!$item) {
            return null;
        }
        $collection=$this->prepareResults(array($item));
        return array_pop($collection);
    }

    protected function prepareResults($collection)
    {
        $result=array();
                
        foreach ($collection as $item) {
            $result[$item->id]=new $this->entity($item->getData());
        }
        return $result;
    }

    public function getFilteredFields($data)
    {
        if (empty($this->allowedFields)) {
            throw new \LogicException('allowedFields is empty in '.get_called_class());
        }
        foreach ($data as $k => $v) {
            if (!in_array($k, $this->allowedFields)) {
                unset($data[$k]);
            }
        }
        return $data;
    }
}

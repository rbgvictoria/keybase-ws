<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ProjectItemModel
 *
 * @author Niels Klazenga <Niels.Klazenga@rbg.vic.gov.au>
 */
class ProjectItemModel extends CI_Model {
    
    protected $insertedItems;
    protected $insertedProjectItems;
    protected $updatedProjectItems;
    
    public function __construct() {
        parent::__construct();
        $this->insertedItems = 0;
        $this->insertedProjectItems = 0;
        $this->updatedProjectItems = 0;
    }
    
    public function loadProjectItems($project, $data) {
        foreach ($data as $row) {
            if (is_object($row)) {
                $this->addOrEditProjectItem($project, $row->name, $row->url);
            }
            else {
                $this->addOrEditProjectItem($project, $row[0], $row[1]);
            }
        }
        return array(
            'itemsInserted' => $this->insertedItems,
            'projectItemsInserted' => $this->insertedProjectItems,
            'projectItemsUpdated' => $this->updatedProjectItems,
        );
    }
    
    public function addOrEditProjectItem($project, $name, $url)
    {
        $item = $this->findItem($name);
        if (!$item) {
            $item = $this->insertItem($name);
        }
        $projectItem = $this->findProjectItem($project, $name);
        if ($projectItem) {
            $this->updateProjectItem($projectItem, $item, $name, $url);
        }
        else {
            $this->insertProjectItem($project, $item, $name, $url);
        }
    }
    
    protected function findProjectItem($project, $name)
    {
        $this->db->select('ProjectItemsID');
        $this->db->from('projectitems');
        $this->db->where('ProjectsID', $project);
        $this->db->where('ScientificName', $name);
        $query = $this->db->get();
        if ($query->num_rows()) {
            $row = $query->row();
            return $row->ProjectItemsID;
        }
        else {
            return false;
        }
    }
    
    protected function findItem($name)
    {
        $this->db->select('ItemsID');
        $this->db->from('items');
        $this->db->where('Name', $name);
        $query = $this->db->get();
        if ($query->num_rows()) {
            $row = $query->row();
            return $row->ItemsID;
        }
        else {
            return false;
        }
    }
    
    protected function insertItem($name) {
        $id = $this->getNewItemID();
        $insert = array(
            'ItemsID' => $id,
            'Name' => $name,
            'TimestampCreated' => date('Y-m-d H:i:s'),
        );
        $this->db->insert('items', $insert);
        $this->insertedItems += $this->db->affected_rows();
        return ($id);
    }
    
    protected function getNewItemID()
    {
        $this->db->select('max(ItemsID)+1 as newID', false);
        $this->db->from('items');
        $query = $this->db->get();
        $row = $query->row();
        return $row->newID;
    }
    
    protected function updateProjectItem($projectItem, $item, $name, $url)
    {
        $update = array(
            'ItemsID' => $item,
            'ScientificName' => $name,
            'Url' => $url,
            'TimestampModified' => date('Y-m-d H:i:s'),
        );
        $this->db->where('ProjectItemsID', $projectItem);
        $this->db->update('projectitems', $update);
        $this->updatedProjectItems += $this->db->affected_rows();
    }
    
    protected function insertProjectItem($project, $item, $name, $url)
    {
        $insert = array(
            'ProjectsID' => $project,
            'ItemsID' => $item,
            'ScientificName' => $name,
            'Url' => $url,
            'TimestampCreated' => date('Y-m-d H:i:s'),
        );
        $this->db->insert('projectitems', $insert);
        $this->insertedProjectItems += $this->db->affected_rows();
        
    }
    
}

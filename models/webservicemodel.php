<?php

class WebServiceModel extends CI_Model {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function getProjectDetails($keyid) {
        $this->db->select('p.ProjectsID AS project_id, p.Name AS project_name, p.ProjectIcon AS project_icon');
        $this->db->from('projects p');
        $this->db->join('keys k', 'p.ProjectsID=k.ProjectsID');
        $this->db->where('k.KeysID', $keyid);
        $query = $this->db->get();
        return $query->row_array();
    }
    
}
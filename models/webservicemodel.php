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
    
    public function getProjectUsers($projectid) {
        $this->db->select("pu.ProjectsUsersID AS project_user_id, u.UsersID AS user_id, 
            CONCAT(u.FirstName, ' ', u.LastName) AS full_name, IF(pu.Role='User', 'Contributor', pu.Role) AS role", FALSE);
        $this->db->from('projects_users pu');
        $this->db->join('users u', 'pu.UsersID=u.UsersID');
        $this->db->where('pu.ProjectsID', $projectid);
        $this->db->order_by('full_name');
        $query = $this->db->get();
        if ($query->num_rows())
            return $query->result_array();
        else
            return FALSE;
    }
    
    public function getSearchResult($searchstring) {
        $this->db->select('k.KeysID AS key_id, k.Name AS key_name, 
            k.TaxonomicScope as taxonomic_scope, k.GeographicScope AS geographic_scope');
        $this->db->from('items i');
        $this->db->join('keys k', 'i.ItemsID=k.TaxonomicScopeID');
        $this->db->join('projects p', 'k.ProjectsID=p.ProjectsID');
        $this->db->where("i.Name LIKE '$searchstring'", FALSE, FALSE);
        $query = $this->db->get();
        if ($query->num_rows()) {
            return $query->result();
        }
        else
            return FALSE;
    }
    
    public function getProject($project) {
        $this->db->select('p.ProjectsID AS project_id, p.Name AS project_name, 
            p.ProjectIcon AS project_icon');
        $this->db->from('projects p');
        $this->db->where('p.ProjectsID', $project);
        $query = $this->db->get();
        if ($query->num_rows()) {
            return $query->row();
        }
        else
            return FALSE;
    }
    
    public function autocompleteItemName($q) {
        $this->db->select('i.Name');
        $this->db->from('keys k');
        $this->db->join('items i', 'k.TaxonomicScopeID=i.ItemsID');
        $this->db->like('i.Name', $q, 'after');
        $this->db->group_by('Name');
        $query = $this->db->get();
        
        if ($query->num_rows()) {
            $ret = array();
            foreach ($query->result() as $row) {
                $ret[] = $row->Name;
            }
            return $ret;
        }
    }
    
    public function getUser($user) {
        $this->db->select("u.UsersID AS user_id, CONCAT(u.FirstName, ' ', u.LastName) AS full_name", FALSE);
        $this->db->from('users u');
        $this->db->where('u.UsersID', $user);
        $query = $this->db->get();
        if ($query->num_rows()) {
            return $query->row();
        }
        else {
            return FALSE;
        }
    }
    
    
    

}
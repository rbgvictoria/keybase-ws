<?php

require_once 'webservicemodel.php';

class ProjectModel extends WebServiceModel {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function getProjects() {
        $this->db->select('p.ProjectsID as project_id, p.Name as project_name, p.ProjectIcon as project_icon, 
            count(DISTINCT k.KeysID) AS num_keys, count(DISTINCT l.ItemsID) AS num_items');
        $this->db->from('projects p');
        $this->db->join('keys k', 'p.ProjectsID=k.ProjectsID', 'left');
        $this->db->join('leads l', 'k.KeysID=l.KeysID', 'left');
        $this->db->where('p.ParentID IS NOT NULL', FALSE, FALSE);
        $this->db->group_by('p.ProjectsID');
        $this->db->order_by('num_keys', 'desc');
        $query = $this->db->get();
        if ($query->num_rows()) {
            $ret = array();
            foreach ($query->result() as $row) {
                $this->db->select('count(distinct UsersID) AS num_users', FALSE);
                $this->db->from('projects_users');
                $this->db->where('ProjectsID', $row->project_id);
                $q = $this->db->get();
                $ret[] = (object) array_merge((array) $row, $q->row_array());
            }
            return $ret;
        }
    }
    
    public function getNumberOfItems() {
        $query = $this->db->query("SELECT count(DISTINCT i.ItemsID) as NumItems
            FROM projects p
            JOIN `keys` k ON p.ProjectsID=k.ProjectsID
            JOIN leads l ON l.KeysID=k.KeysID
            LEFT JOIN groupitem g0 ON l.ItemsID=g0.GroupID AND g0.OrderNumber=0
            LEFT JOIN groupitem g1 ON l.ItemsID=g1.GroupID AND g1.OrderNumber=1
            JOIN items i ON coalesce(g0.MemberID, g1.MemberID, l.ItemsID)=i.ItemsID");
        $row = $query->row();
        return $row->NumItems;
    }
    
    public function editProject($data) {
        $projectid = isset($data['projectid']) ? $data['projectid'] : FALSE;
        $taxonomicscopeid = NULL;
        if ($data['taxonomicscope']) {
            $this->db->select('ItemsID');
            $this->db->from('items');
            $this->db->where('Name', $data['taxonomicscope']);
            $query = $this->db->get();
            if ($query->num_rows()) {
                $row = $query->row();
                $taxonomicscopeid = $row->ItemsID;
            }
            else {
                $this->db->select('MAX(ItemsID) AS max', FALSE);
                $this->db->from('items');
                $query = $this->db->get();
                $row = $query->row();
                $taxonomicscopeid = $row->max + 1;
                
                $this->db->insert('items', array('ItemsID' => $taxonomicscopeid, 'Name' => $data['taxonomicscope']));
            }
        }
        
        $updateArray = array(
            'Name' => $data['name'],
            'TaxonomicScope' => $data['taxonomicscope'],
            'TaxonomicScopeID' => $taxonomicscopeid,
            'GeographicScope' => $data['geographicscope'],
            'Description' =>$data['description'],
        );
        
        if ($projectid) {
            $this->db->where('ProjectsID', $projectid);
            $this->db->update('projects', $updateArray);
        }
        else {
            $updateArray['ParentID'] = 3;
            
            $this->db->select('MAX(ProjectsID)+1 AS NewID');
            $this->db->from('projects');
            $q = $this->db->get();
            $r = $q->row();
            $projectid = $r->NewID;
            $updateArray['ProjectsID'] = $projectid;
            $this->db->insert('projects', $updateArray);

            $insertArray = array(
                'ProjectsID' => $projectid,
                'UsersID' => $data['userid'],
                'Role' => 'Manager',
            );
            $this->db->insert('projects_users', $insertArray);
        }
        return $projectid;
    }
    
    public function deleteProject($project, $userid) {
        $check = ($userid == 1) ? TRUE : FALSE;
        if ($check) {
            $this->db->trans_start();
            $this->db->select('KeysID');
            $this->db->from('keys');
            $this->db->where('ProjectsID', $project);
            $query = $this->db->get();
            if ($query->num_rows()) {
                foreach ($query->result() as $row) {
                    $key = $row->KeysID;
                    $this->db->where('KeysID', $key);
                    $this->db->delete('leads');
                    $this->db->where('KeysID', $key);
                    $this->db->delete('keys');
                }
            }
            $this->db->where('ProjectsID', $project);
            $this->db->delete('projects');
            $this->db->trans_complete();
            return TRUE;
        }
        else {
            return FALSE;
        }
    }
    
    public function getProjectKeys($project) {
        $ret = array();
        $query = $this->db->query("SELECT k.KeysID, k.Name, k.TaxonomicScopeID, i.Name AS TaxonomicScope,
                s.KeysID AS ParentKeyID, s.Name AS ParentKeyName, k.CreatedByID,
                concat(u.FirstName, ' ', u.LastName) AS CreatedBy
            FROM `keys` k
            LEFT JOIN (
                SELECT slk.KeysID AS KeyID, sk.KeysID, sk.Name, sk.TaxonomicScopeID
                FROM `keys` sk
                JOIN leads sl ON sk.KeysID=sl.KeysID
                LEFT JOIN `keys` slk ON sl.ItemsID=slk.TaxonomicScopeID AND sk.ProjectsID=slk.ProjectsID
                WHERE sk.ProjectsID=$project AND slk.KeysID IS NOT NULL
                GROUP BY KeyID
            ) as s ON k.KeysID=s.KeyID
            LEFT JOIN items i ON k.TaxonomicScopeID=i.ItemsID
            LEFT JOIN users u ON k.CreatedByID=u.UsersID
            WHERE k.ProjectsID=$project
            ORDER BY k.Name");
            
        if ($query->num_rows()) {
            foreach ($query->result() as $row) {
                $key = array();
                $key['id'] = $row->KeysID;
                $key['parent_id'] = $row->ParentKeyID;
                $key['name'] = $row->Name;
                $key['taxonomic_scope'] = (object) array(
                    'id' => $row->TaxonomicScopeID,
                    'name' => $row->TaxonomicScope
                );
                $key['created_by'] = (object) array(
                    'id' => $row->CreatedByID,
                    'name' => $row->CreatedBy
                );
                $ret[] = (object) $key;
            }
        }
        return $ret;
    }
    
    public function getFirstKey($project) {
        $firstKey = array(
            'id' => NULL,
            'name' => NULL
        );
        $this->db->select('k.KeysID, k.Name');
        $this->db->from('projects p');
        $this->db->join('keys k', 'p.TaxonomicScopeID=k.TaxonomicScopeID AND p.ProjectsID=k.ProjectsID', 'left');
        $this->db->where('p.ProjectsID', $project);
        $query = $this->db->get();
        if ($query->num_rows()) {
            $row = $query->row();
            $firstKey['id'] = $row->KeysID;
            $firstKey['name'] = $row->Name;
        }
        return $firstKey;
    }
    
    public function addProjectUser($data) {
        if ($this->checkPrivileges($data['project'], $data['keybase_user_id'])) {
            $this->db->select('count(*) as num', FALSE);
            $this->db->from('projects_users');
            $this->db->where('ProjectsID', $data['project']);
            $this->db->where('UsersID', $data['user']);
            $query = $this->db->get();
            $row = $query->row();
            if (!$row->num) {
                $q = $this->db->query("SELECT MAX(ProjectsUsersID)+1 AS newid FROM projects_users");
                $r = $q->row();
                $newId = $r->newid;
                $insertArray = array(
                    'ProjectsUsersID' => $newId,
                    'ProjectsID' => $data['project'],
                    'UsersID' => $data['user'],
                    'Role' => $data['role']
                );
                $this->db->insert('projects_users', $insertArray);
                return $newId;
            }
        }
    }
    
    public function deleteProjectUser($id, $data) {
        if ($this->checkPrivileges($data['project'], $data['keybase_user_id'])) {
            $this->db->where('ProjectsUsersID', $id);
            $this->db->delete('projects_users');
            return $id;
        }
    }
    
    protected function checkPrivileges($project, $user) {
        $this->db->select('count(*) as num', FALSE);
        $this->db->from('projects_users');
        $this->db->where('ProjectsID', $project);
        $this->db->where('UsersID', $user);
        $this->db->where('Role', 'Manager');
        $query = $this->db->get();
        $row = $query->row();
        return $row->num ? TRUE : FALSE;
    }
    
}

/* End of file filtermodel.php */
/* Location: ./models/filtermodel.php */

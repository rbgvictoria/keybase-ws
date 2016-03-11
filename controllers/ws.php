<?php

class WS extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->helper('json');
        $this->load->model('keymodel');
    }
    
    public function key($id=FALSE) {
        if (!$id) {
            $id = $this->input->get('key_id');
        }
        if (!$id) {
            exit();
        }
        $data = $this->keymodel->getKey($id);
        $data->project = (object) $this->keymodel->getProjectDetails($id);
        $data->project->project_icon = 'http://keybase.rbg.vic.gov.au/images/projecticons/' . $data->project->project_icon;
        $data->source = (object) $this->keymodel->getSource($id);
        $data->source->is_modified = ($data->source->is_modified) ? true : false;
        $data->source->citation = $this->keymodel->getCitation($id);
        $data->items = $this->keymodel->getKeyItems($id);
        $data->first_step = $this->keymodel->getRootNode($id);
        $data->leads = $this->keymodel->getLeads($id);
        echo json_output($data);
    }
    
    public function key_post($id=FALSE) {
        $keyid = $this->key_meta_post($id);
        if (!$id) {
            $id = $keyid;
        }
        if (isset($_FILES['file_content']['tmp_name'])) {
            $this->load->library('KeyUploadService');
            if ($_FILES['file_content']['type'] == 'text/csv') {
                $result = $this->keyuploadservice->loadKey($id, $_FILES['file_content']['tmp_name'], 'delimitedtext');
            }
        }
        echo json_output($keyid);
    }
    
    public function key_delete($id) {
        $result = $this->keymodel->deleteKey($id, $this->input->post('keybase_user_id'));
        echo json_output($result);
    }
    
    public function key_meta($id) {
        $data = $this->keymodel->getKey($id);
        $data->created_by = $this->keymodel->getUser($data->created_by_id);
        unset($data->created_by_id);
        $data->modified_by = $this->keymodel->getUser($data->modified_by_id);
        unset($data->modified_by_id);
        $data->source = (object) $this->keymodel->getSource($id);
        $data->source->is_modified = ($data->source->is_modified) ? true : false;
        $data->source->citation = $this->keymodel->getCitation($id);
        $data->project = (object) $this->keymodel->getProjectDetails($id);
        $data->project->project_icon = 'http://keybase.rbg.vic.gov.au/images/projecticons/' . $data->project->project_icon;
        $data->breadcrumbs = $this->keymodel->getBreadCrumbs($id);
        
        $changes = $this->keymodel->getChanges($id);
        $data->changes = ($changes) ? $changes : null;
        echo json_output($data);
    }
    
    public function key_meta_post($id) {
        $this->session->unset_userdata('id');
        $this->session->set_userdata('id', $this->input->post('keybase_user_id'));
        $keyMetadata = json_decode($this->input->post('key_metadata'));
        $result = $this->keymodel->editKeyMetadata($keyMetadata, $this->session->userdata('id'));
        return $result;
    }
    
    public function project_users($project) {
        $data = $this->keymodel->getProjectUsers($project);
        echo json_output($data);
    }
    
    public function search_items($searchstring) {
        $data = $this->keymodel->getSearchResult($searchstring);
        foreach ($data as $index => $row) {
            $row->project = $this->keymodel->getProjectDetails($row->key_id);
            $data[$index] = $row;
        }
        echo json_output($data);
    }
    
    public function autocomplete_item_name() {
        if (!$this->input->get('term')) exit;
        $q = strtolower($this->input->get('term'));
        $items = $this->keymodel->autoCompleteItemName($q);
        echo json_output($items);
    }

    public function export($format, $keyid) {
        $this->output->enable_profiler(FALSE);
        $this->load->model('exportmodel');
        $key = $this->exportmodel->export($keyid);
        
        $this->load->library('ExportService');
        
        if ($format == 'lpxk') {
            $lpxk = $this->exportservice->exportToLpxk($key);
            header('Content-type: text/xml');
            echo $lpxk;
        }
        elseif ($format == 'csv') {
            $filename = 'keybase_export_' . $keyid . '_' . time() . '.csv';
            $csv = $this->exportservice->exportToCsv($key);
            header('Content-type: text/csv');
            header('Content-disposition: attachment;filename=' . $filename);
            echo $csv;
        }
        elseif ($format == 'txt') {
            $filename = 'keybase_export_' . $keyid . '_' . time() . '.txt';
            $csv = $this->exportservice->exportToCsv($key, 'tab');
            header('Content-type: text/plain');
            header('Content-disposition: attachment;filename=' . $filename);
            echo $csv;
        }
        elseif ($format == 'sdd') {
            $sdd = $this->exportservice->exportToSdd($key);
            header('Content-type: text/xml');
            echo $sdd;
        }
    }
    
}


/* End of file keys.php */
/* Location: ./controllers/keys.php */
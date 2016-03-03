<?php

class WS extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
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
        $data['project'] = (object) $this->keymodel->getProjectDetails($id);
        $data['project']->project_icon = 'http://keybase.rbg.vic.gov.au/images/projecticons/' . $data['project']->project_icon;
        $data['source'] = (object) $this->keymodel->getSource($id);
        $data['source']->is_modified = ($data['source']->is_modified) ? true : false;
        $data['source']->citation = $this->keymodel->getCitation($id);
        $data['items'] = $this->keymodel->getKeyItems($id);
        $data['first_step'] = $this->keymodel->getRootNode($id);
        $data['leads'] = $this->keymodel->getLeads($id);
        echo json_output($data);
    }
    
    public function key_meta($id) {
        $data = $this->keymodel->getKey($id);
        $data['source'] = (object) $this->keymodel->getSource($id);
        $data['source']->is_modified = ($data['source']->is_modified) ? true : false;
        $data['source']->citation = $this->keymodel->getCitation($id);
        $data['project'] = (object) $this->keymodel->getProjectDetails($id);
        $data['project']->project_icon = 'http://keybase.rbg.vic.gov.au/images/projecticons/' . $data['project']->project_icon;
        $data['breadcrumbs'] = $this->keymodel->getBreadCrumbs($id);
        $changes = $this->keymodel->getChanges($id);
        $data['changes'] = ($changes) ? $changes : null;
        echo json_output($data);
    }
    
    
}


/* End of file keys.php */
/* Location: ./controllers/keys.php */
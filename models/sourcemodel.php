<?php

require_once 'webservicemodel.php';

class SourceModel extends WebServiceModel {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function getSource($id) {
        $this->_select();
        $this->db->from('sources');
        $this->db->where('SourcesID', $id);
        $query = $this->db->get();
        
        if ($query->num_rows()) {
            return $query->row_array();
        }
    }
    
    public function editSource($data) {
        $obj = new stdClass();
        foreach($data as $key => $value) {
            $this->setObjectVariable($obj, $key, $value);
        }
        if (isset($data['id'])) {
            unset($obj->Id);
            $this->db->where('SourcesID', $data['id']);
            $this->db->update('sources', $obj);
            return (integer) $data['id'];
        }
        else {
            $this->db->insert('sources', $obj);
            return $this->db->insert_id();
        }
        return $obj;
    }
    
    private function setObjectVariable($obj, $key, $value) {
        $key = str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        switch ($key) {
            case 'Author':
                $key = 'Authors';
                break;
            case 'PublicationYear':
                $key = 'Year';
                break;
            case 'InAuthor':
                $key = 'InAuthors';
                break;
            case 'Page':
                $key = 'Pages';
                break;
            case 'ProjectId':
                $key = 'ProjectsID';
                break;
            default:
                break;
        }
        $obj->$key = ($value) ? $value : NULL;
        return $obj;
    }
    
    public function autocomplete($term, $project=FALSE) {
        $ret = array();
        
        /* Split off year */
        if (preg_match('/[0-9]+$/', $term, $matches)) {
            $year = $matches[0];
            $author = trim(substr($term, 0, strpos($term, $matches[0])));
        }
        else {
            $author = $term;
            $year = false;
        }
        
        $this->_select();
        $this->db->from('sources');
        if ($project) {
            $this->db->where('ProjectsID', $project);
        }
        $this->db->like('Authors', $author, 'after');
        if ($year) {
            $this->db->like('Year', $year, 'after');
        }
        $this->db->order_by('Authors', 'Year');
        $query = $this->db->get();
        if ($query->num_rows()) {
            foreach ($query->result() as $row) {
                $ret[] = array(
                    'value' => $row->id,
                    'label' => $row->author . ' (' . $row->publication_year . ')',
                    'description' => $this->getDescription($row)
                );
            }
        }
        return $ret;
    }
    
    private function _select() {
        $this->db->select("SourcesID as id, Authors AS author, Year AS publication_year, Title AS title, InAuthors AS in_author, 
            InTitle AS in_title, Edition AS edition, Journal AS journal, Series AS series, Volume AS volume, 
            Part AS part, Publisher AS publisher, PlaceOfPublication AS place_of_publication, Pages AS page, 
            Url AS url");
    }
    
    private function getDescription($source) {
        $ret = '';
        if ($source->journal) {
            $ret .= $source->title . '. <i>' . $source->journal . '</i>';
            if ($source->series)
                $ret .= ', ser. ' . $source->series;
            $ret .= ' <b>' . $source->volume . '</b>';
            if ($source->part) 
                $ret .= '(' . $source->part . ')';
            $ret .= ':' . $source->page . '.';
        }
        elseif ($source->in_title) {
            $ret .= $source->title . '. In: ';
            if ($source->in_author) 
                $ret .= $source->in_author . ', ';
            $ret .= '<i>' . $source->in_title . '</i>';
            if ($source->volume) 
                $ret .= ' <b>' . $source->volume . '</b>';
            if ($source->page)
                $ret .= ', pp. ' . $source->page;
            $ret .= '.';
            if ($source->publisher) {
                $ret .= ' ' . $source->publisher;
                if ($source->place_of_publication)
                    $ret .= ', ';
                else
                    $ret .= '.';
            }
            if ($source->place_of_publication)
                $ret .= ' ' . $source->place_of_publication . '.';
        }
        else {
            $ret .= '<i>' . $source->title . '</i>.';
            if ($source->publisher) {
                $ret .= ' ' . $source->publisher;
                if ($source->place_of_publication)
                    $ret .= ', ';
                else
                    $ret .= '.';
            }
            if ($source->place_of_publication)
                $ret .= ' ' . $source->place_of_publication . '.';
        }
        return $ret;
    }
}


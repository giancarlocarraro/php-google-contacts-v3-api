<?php

namespace rapidweb\googlecontacts\factories;

use rapidweb\googlecontacts\helpers\GoogleHelper;
use rapidweb\googlecontacts\objects\Contact;

abstract class ContactFactory
{
    public static function initParam($_RefreshToken=null)
    {
        GoogleHelper::loadConfig($_RefreshToken);
    }
    
    //Function With return email of account conected
    public static function getUserId()
    {
        $client = GoogleHelper::getClient();
        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/thin?max-results=1&updated-min=2007-03-16T00:00:00');
        $val = $client->getAuth()->authenticatedRequest($req);
        $response = $val->getResponseBody();        
        $xmlContacts = simplexml_load_string($response);
        return (string) $xmlContacts->id;
    }
        
    public static function getAll()
    {
        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full?max-results=10000&updated-min=2007-03-16T00:00:00');

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();
        $xmlContacts = simplexml_load_string($response);
        $xmlContacts->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $contactsArray = array();

        foreach ($xmlContacts->entry as $xmlContactsEntry) {
            $contactDetails = array();

            $contactDetails['id'] = (string) $xmlContactsEntry->id;
            $contactDetails['name'] = (string) $xmlContactsEntry->title;

            foreach ($xmlContactsEntry->children() as $key => $value) {
                $attributes = $value->attributes();

                if ($key == 'link') {
                    if ($attributes['rel'] == 'edit') {
                        $contactDetails['editURL'] = (string) $attributes['href'];
                    } elseif ($attributes['rel'] == 'self') {
                        $contactDetails['selfURL'] = (string) $attributes['href'];
                    } elseif ($attributes['rel'] == 'http://schemas.google.com/contacts/2008/rel#edit-photo') {
                        $contactDetails['photoURL'] = (string) $attributes['href'];
                    }
                }
            }

            $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');            
            foreach ($contactGDNodes as $key => $value) {
                switch ($key) {
                    case 'organization':
                        $contactDetails[$key]['orgName'] = (string) $value->orgName;
                        $contactDetails[$key]['orgTitle'] = (string) $value->orgTitle;
                        break;
                    case 'email':
                        $attributes = $value->attributes();
                        $emailadress = (string) $attributes['address'];
                        $emailtype = substr(strstr($attributes['rel'], '#'), 1);
                        $contactDetails[$key][] = ['type' => $emailtype, 'email' => $emailadress];
                        break;
                    case 'phoneNumber':
                        $attributes = $value->attributes();
                        //$uri = (string) $attributes['uri'];
                        $type = substr(strstr($attributes['rel'], '#'), 1);
                        //$e164 = substr(strstr($uri, ':'), 1);
                        $contactDetails[$key][] = ['type' => $type, 'number' => $value->__toString()];
                        break;
                    default:
                        $contactDetails[$key] = (string) $value;
                        break;
                }
            }

            $contactsArray[] = new Contact($contactDetails);
        }

        return $contactsArray;
    }
    
    //New function with return only name, email, phonenumber, organization name,  
    //selfURL, editURL(sufix)
    //obs: editURL = selfURL + editURL(sufix)
    public static function getAllSimple()
    {
        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full?max-results=10000&updated-min=2007-03-16T00:00:00');

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();
        $xmlContacts = simplexml_load_string($response);
        $xmlContacts->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $contactsArray = array();

        foreach ($xmlContacts->entry as $xmlContactsEntry) {
            $contactDetails = array();
            //$contactDetails['id'] = (string) $xmlContactsEntry->id;
            $contactDetails['name'] = (string) $xmlContactsEntry->title;
            foreach ($xmlContactsEntry->children() as $key => $value) {
                $attributes = $value->attributes();
                if ($key == 'link') {
                    if ($attributes['rel'] == 'edit') {
                        $contactDetails['editURL'] = (string) $attributes['href'];
                    } elseif ($attributes['rel'] == 'self') {
                        $contactDetails['selfURL'] = (string) $attributes['href'];
                    }
//                    } elseif ($attributes['rel'] == 'http://schemas.google.com/contacts/2008/rel#edit-photo') {
//                        $contactDetails['photoURL'] = (string) $attributes['href'];
//                    }
                }
            }
            $contactDetails['editURL'] = substr($contactDetails['editURL'], strlen($contactDetails['selfURL']));
            
            
            $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');            
            foreach ($contactGDNodes as $key => $value) {
                switch ($key) {
                    case 'organization':
                        $contactDetails[$key] = (string) $value->orgName;                        
                        break;
                    case 'email':
                        $attributes = $value->attributes();
                        $emailadress = (string) $attributes['address'];
                        $contactDetails[$key] = $emailadress;
                        break;
                    case 'phoneNumber':
                        $attributes = $value->attributes();
                        $contactDetails[$key] = $value->__toString();
                        break;
                    default:
                        $contactDetails[$key] = (string) $value;
                        break;
                }
            }

            $contactsArray[] = new Contact($contactDetails);
        }

        return $contactsArray;
    }

    public static function getBySelfURL($selfURL)
    {
        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request($selfURL);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'http://schemas.google.com/contacts/2008/rel#edit-photo') {
                    $contactDetails['photoURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');
        foreach ($contactGDNodes as $key => $value) {
            switch ($key) {
                case 'organization':
                    $contactDetails[$key]['orgName'] = (string) $value->orgName;
                    $contactDetails[$key]['orgTitle'] = (string) $value->orgTitle;
                    break;
                case 'email':
                    $attributes = $value->attributes();
                    $emailadress = (string) $attributes['address'];
                    $emailtype = substr(strstr($attributes['rel'], '#'), 1);
                    $contactDetails[$key][] = ['type' => $emailtype, 'email' => $emailadress];
                    break;
                case 'phoneNumber':
                    $attributes = $value->attributes();
                    $uri = (string) $attributes['uri'];
                    $type = substr(strstr($attributes['rel'], '#'), 1);
                    $e164 = substr(strstr($uri, ':'), 1);
                    $contactDetails[$key][] = ['type' => $type, 'number' => $e164];
                    break;
                default:
                    $contactDetails[$key] = (string) $value;
                    break;
            }
        }

        return new Contact($contactDetails);
    }

    //update data of contact, 
    //append phoneNumber, organizationName possibility of update in function
    public static function submitUpdates(Contact $updatedContact)
    {
        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request($updatedContact->selfURL);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $xmlContactsEntry->title = $updatedContact->name;

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');
       
        //variables control of optional data of contact
        $v_SetPhone = false;
        $v_SetOrganization = false;
        $v_SetOrgName = false;
        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();
            if ($key == 'email') {
                $attributes['address'] = $updatedContact->email;                            
            } else if ($key == 'phoneNumber') { 
                $v_SetPhone = true;
                if (!is_null($updatedContact->phoneNumber) && $updatedContact->phoneNumber != '')
                { 
                    $value[0] = $updatedContact->phoneNumber;                     
                }
            } else if ($key == 'organization') {                 
                $v_SetOrganization = true;                   
                foreach ( $value->children('http://schemas.google.com/g/2005') as $key2 => $value2)
                {
                    if ($key2 == 'orgName') {                        
                        $v_SetOrgName = true;
                        $value2[0] = $updatedContact->organization; 
                    }
                }
                if (!$v_SetOrgName && !is_null($updatedContact->organization))
                {
                    $v_SetOrgName = true;
                    $value->addChild('orgName', $updatedContact->organization, 'http://schemas.google.com/g/2005');
                }
                
            }
            else {
                $xmlContactsEntry->$key = $updatedContact->$key;
                $attributes['uri'] = '';
            }
        }
        
         
        if (!$v_SetPhone && !is_null($updatedContact->phoneNumber) && $updatedContact->phoneNumber != '')
        {            
            $v_child = $xmlContactsEntry->addChild('phoneNumber', $updatedContact->phoneNumber, 'http://schemas.google.com/g/2005');
            $v_child->addAttribute('rel', 'http://schemas.google.com/g/2005#work');            
        }

        if (!$v_SetOrgName && !is_null($updatedContact->organization))
        {   
            $v_child = $xmlContactsEntry->addChild('organization', null, 'http://schemas.google.com/g/2005');
            $v_child->addAttribute('rel', 'http://schemas.google.com/g/2005#work');
            
            if (!$v_SetOrgName)
            {
                $v_child->addChild('orgName', $updatedContact->organization, 'http://schemas.google.com/g/2005');                
            }            
        }        
        
        $updatedXML = $xmlContactsEntry->asXML();

        $req = new \Google_Http_Request($updatedContact->editURL);
        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
        $req->setRequestMethod('PUT');
        $req->setPostBody($updatedXML);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();        

        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');

        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();
            if ($key == 'organization') {
                $contactDetails[$key] = (string) $value->orgName;
            } else 
            if ($key == 'email') {
                $contactDetails[$key] = (string) $attributes['address'];
            } else {
                $contactDetails[$key] = (string) $value;
            }
        }

        return new Contact($contactDetails);
    }

    //add organizationName information in creation of contact
    public static function create($name, $phoneNumber, $emailAddress, $organization = null)
    {
        $doc = new \DOMDocument();
        $doc->formatOutput = true;
        $entry = $doc->createElement('atom:entry');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
        $doc->appendChild($entry);

        $title = $doc->createElement('title', $name);
        $entry->appendChild($title);

        $email = $doc->createElement('gd:email');
        $email->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
        $email->setAttribute('address', $emailAddress);
        $entry->appendChild($email);

        if (!is_null($phoneNumber) && $phoneNumber != '')
        {
        $contact = $doc->createElement('gd:phoneNumber', $phoneNumber);
        $contact->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
        $entry->appendChild($contact);
        }

        if (!is_null($organization))
        {            
            $orgname = $doc->createElement('gd:orgName', $organization); 
            $contact = $doc->createElement('gd:organization', '');
            $contact->setAttribute('rel', 'http://schemas.google.com/g/2005#other');  
            $contact->appendChild($orgname);
            $entry->appendChild($contact);
        }
        
        
        $xmlToSend = $doc->saveXML();
        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full');
        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
        $req->setRequestMethod('POST');
        $req->setPostBody($xmlToSend);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();        
        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');

        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();
            if ($key == 'organization') {
                $contactDetails[$key] = (string) $value->orgName;
            } else 
            if ($key == 'email') {
                $contactDetails[$key] = (string) $attributes['address'];
            } else {
                $contactDetails[$key] = (string) $value;
            }
        }

        return new Contact($contactDetails);
    }
    
    public static function delete(Contact $toDelete)
    {
        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request($toDelete->editURL);
        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
        $req->setRequestMethod('DELETE');

        $client->getAuth()->authenticatedRequest($req);
    }
    
    public static function getPhoto($photoURL)
    {
        $client = GoogleHelper::getClient();
        $req = new \Google_Http_Request($photoURL);
        $req->setRequestMethod('GET');
        $val = $client->getAuth()->authenticatedRequest($req);
        $response = $val->getResponseBody();
        return $response;
    }
}

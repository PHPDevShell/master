<?php

class PHPDS_tagger extends PHPDS_dependant
{
    const tag_user  = 'user';
    const tag_node  = 'node';
    const tag_role  = 'role';
    const tag_group = 'group';

    /**
     * Generic setter/getter for the tags.
     * All parameters must be given explicitly
     *
     * As a setter, all 4 params must be given (for the [$object;$target] set the tag name $name to value $value)
     * As a getter, don't give the value ; a single value will be returned
     *
     * @param string $object
     * @param string $name
     * @param string|int $target
     * @param string $value (optional)
     * @return string|array|null
     */
    public function tag($object, $name, $target, $value = null)
    {
        $parameters = array('object' => $object, 'name' => $name, 'target' => $target);
        if (!is_null($value)) {
            $parameters['value'] = $value;
            return $this->db->invokeQueryWith('PHPDS_taggerMarkQuery', $parameters);
        } else {
            return $this->db->invokeQueryWith('PHPDS_taggerLookupQuery', $parameters);
        }
    }

    /**
     * Lookup tags based on criteria; returns an array
     *
     * @param string $object (optional)
     * @param string $name   (optional)
     * @param string|int $target (optional)
     * @param string $value  (optional)
     *
     * @return string|null
     */
    public function tagLookup($object = null, $name = null, $target = null, $value = null)
    {
        $parameters = array('object' => $object, 'name' => $name, 'target' => $target, 'value' => $value);

        return $this->db->invokeQueryWith('PHPDS_taggerLookupQuery', $parameters);
    }

    /**
     * List of [object;target] for the given tag (optionally restricted to the given $object/$target)
     * @param string $name
     * @param string $object
     * @param string|int $target
     * @return array
     */
    public function tagList($name, $object, $target = null)
    {
        $parameters = array('object' => $object, 'name' => $name, 'target' => $target);
        $result     = $this->db->invokeQueryWith('PHPDS_taggerListQuery', $parameters);
        if (!is_array($result)) $result = array($result);
        return $result;
    }

    /**
     * Tag (set/get) the user specified in $target
     *
     * @param string $name
     * @param string|int $target
     * @param mixed $value
     * @return mixed
     */
    public function tagUser($name, $target, $value = null)
    {
        return $this->tag(PHPDS_tagger::tag_user, $name, $target, $value);
    }

    /**
     * Tag (set/get) the current user
     *
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function tagMe($name, $value = null)
    {
        $me = $this->user->currentUserID();
        if (empty($me)) return false;
        return $this->tag(PHPDS_tagger::tag_user, $name, $me, $value);
    }

    /**
     * Tag (set/get) the node specified in $target
     *
     * @param string $name
     * @param string|int $target
     * @param mixed $value
     * @return mixed
     */
    public function tagNode($name, $target, $value = null)
    {
        return $this->tag(PHPDS_tagger::tag_node, $name, $target, $value);
    }

    /**
     * Tag (set/get) the current node
     *
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function tagHere($name, $value = null)
    {
        $here = $this->navigation->currentNodeID();
        if (empty($here)) return false;
        return $this->tag(PHPDS_tagger::tag_node, $name, $here, $value);
    }

    /**
     * Tag (set/get) the role specified in $target
     *
     * @param string $name
     * @param string|int $target
     * @param mixed $value
     * @return mixed
     */
    public function tagRole($name, $target, $value = null)
    {
        return $this->tag(PHPDS_tagger::tag_role, $name, $target, $value);
    }

    /**
     * Tag (set/get) the group specified in $target
     *
     * @param string $name
     * @param string|int $target
     * @param mixed $value
     * @return mixed
     */
    public function tagGroup($name, $target, $value = null)
    {
        return $this->tag(PHPDS_tagger::tag_group, $name, $target, $value);
    }

    /**
     * This function creates tag view list with form input fields. Can also store it if available
     *
     * @param string $object
     * @param string $target
     * @param array  $taggernames   Array of names posted by the tagger form.
     * @param array  $taggervalues  Array of values posted by the tagger form.
     * @param array  $taggerids     Array of updated ids posted by the tagger form.
     * @return string
     */
    public function tagArea($object, $target, $taggernames, $taggervalues, $taggerids)
    {
        $mod = $this->template->mod;

        if (!empty($taggernames) && is_array($taggernames)) {
            $this->db->invokeQuery('PHPDS_updateTagsQuery', $object, $target, $taggernames, $taggervalues, $taggerids);
        }

        $taglist = $this->db->invokeQuery('PHPDS_taggerListTargetQuery', $target, $object);

        $tagarea = $mod->taggerArea($taglist, ___('Tag Name'), ___('Tag Value'));

        return $tagarea;
    }

    /**
     * Will store tags when needed.
     *
     * @param   string $object
     * @param   string $target
     * @param   array  $taggernames   Array of names posted by the tagger form.
     * @param   array  $taggervalues  Array of values posted by the tagger form.
     * @param   array  $taggerids     Array of updated ids posted by the tagger form.
     * @return  string
     *
     * @version 1.0
     * @author  jason <titan@phpdevshell.org>
     * @date    20130301
     */
    public function tagUpdate($object, $target, $taggernames, $taggervalues, $taggerids)
    {
        if (!empty($taggernames) && is_array($taggernames)) {
            $this->db->invokeQuery('PHPDS_updateTagsQuery', $object, $target, $taggernames, $taggervalues, $taggerids);
        }
    }

    /**
     * Quick delete action for a single tag.
     *
     * @version 1.0
     * @date 20130220
     * @author  jason <titan@phpdevshell.org>
     *
     * @param $tag_id
     * @return mixed
     */
    public function tagDelete($tag_id)
    {
        return $this->db->invokeQuery('PHPDS_deleteTagsByIdQuery', $tag_id);
    }
}
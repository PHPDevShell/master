<?php

class PHPDS_tagger extends PHPDS_dependant
{
    const tag_user  = 'user';
    const tag_node  = 'node';
    const tag_role  = 'role';

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
        if (!is_null($value)) {
            return $this->markTag($object, $name, $target, $value);
        } else {
            return $this->tagLookup($object, $name, $target);
        }
    }

    public function markTag($object, $name, $target, $value = null)
    {
        $sql = "
            REPLACE INTO _db_core_tags
		    SET tag_object = :tag_object, tag_name = :tag_name, tag_target = :tag_target, tag_value = :tag_value
        ";

        return $this->db->queryAffects($sql,
            array('tag_object' => $object, 'tag_name' => $name, 'tag_target' => $target, 'tag_value' => $value)
        );
    }

    /**
     * Lookup tags based on criteria; returns a string
     *
     * @param string $object (optional)
     * @param string $name   (optional)
     * @param string|int $target (optional)
     *
     * @return string|null
     */
    public function tagLookup($object = null, $name = null, $target = null)
    {
        $sql = "
            SELECT  tag_value
            FROM    _db_core_tags
        ";

        $build[] = ($object != null) ? 'tag_object = :tag_object' : null;
        $build[] = ($name   != null) ? 'tag_name   = :tag_name'   : null;
        $build[] = ($target != null) ? 'tag_target = :tag_target' : null;

        $sql = $this->db->queryBuild($sql, $build);
        return $this->db->querySingle($sql,
            array('tag_object' => $object, 'tag_name' => $name, 'tag_target' => $target)
        );
    }

    /**
     * List of [object;target] for the given tag (optionally restricted to the given $object/$target)
     *
     * @param string $name
     * @param string $object
     * @param string|int $target
     * @return array
     */
    public function tagList($name, $object, $target = null)
    {
        $sql = "
            SELECT  tag_id, tag_object, tag_name, tag_target, tag_value
            FROM    _db_core_tags
        ";

        $build[] = ($object != null) ? 'tag_object = :tag_object' : null;
        $build[] = ($name != null)   ? 'tag_name   = :tag_name'   : null;
        $build[] = ($target != null) ? 'tag_target = :tag_target' : null;

        $sql = $this->db->queryBuild($sql, $build);
        return $this->db->queryFAR($sql,
            array('tag_object' => $object, 'tag_name' => $name, 'tag_target' => $target)
        );
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
     * This function creates tag view list for templates form input fields. Will also store it if available.
     *
     * @param string $object
     * @param string $target
     * @param array  $taggernames   Array of names posted by the tagger form.
     * @param array  $taggervalues  Array of values posted by the tagger form.
     * @param array  $taggerids     Array of updated ids posted by the tagger form.
     * @return array
     */
    public function tagArea($object, $target, $taggernames, $taggervalues, $taggerids)
    {
        $this->tagMultiple($object, $target, $taggernames, $taggervalues, $taggerids);
        $taglist = $this->getMultiple($object, $target);

        return $taglist;
    }

    /**
     * Get multiple tags in an array by passing target and object
     *
     *
     * @param string $object
     * @param string|int $target
     * @return array
     */
    public function getMultiple($object, $target)
    {
        $sql = "
            SELECT  tag_id, tag_name, tag_value
            FROM    _db_core_tags
            WHERE   tag_target = :tag_target
            AND     tag_object = :tag_object
        ";

        return $this->db->queryFAR($sql, array('tag_target' => $target, 'tag_object' => $object));
    }

    /**
     * Adds an array of tags to the tag database at once which connects to a single target and object.
     *
     * @param string $object
     * @param string $target
     * @param array  $taggernames   Array of names posted by the tagger form.
     * @param array  $taggervalues  Array of values posted by the tagger form.
     * @param array  $taggerids     Array of updated ids posted by the tagger form.
     * @return int|bool
     */
    public function tagMultiple($object, $target, $taggernames, $taggervalues, $taggerids)
    {
        $sql = "
            REPLACE INTO _db_core_tags ( tag_id,  tag_object,  tag_name,  tag_target,  tag_value)
		    VALUES                     (:tag_id, :tag_object, :tag_name, :tag_target, :tag_value)
        ";
        $this->db->prepare($sql);
        if (!empty($taggernames) && is_array($taggernames)) {
            if (!empty($target) && !empty($object)) {
                foreach ($taggernames as $key => $name) {
                    if (!empty($name)) {
                        $id    = (!empty($taggerids[$key])) ? $taggerids[$key] : '';
                        $value = (!empty($taggervalues[$key])) ? $taggervalues[$key] : '';
                        $this->db->execute(
                            array('tag_id' => $id, 'tag_object' => $object, 'tag_name' => $name,
                                  'tag_target' => $target, 'tag_value' => $value
                            )
                        );
                    }
                }
                return $this->db->affectedRows();
            }
        } else {
            return false;
        }
        return false;
    }

    /**
     * Quick delete action for a single tag.
     *
     * @param $tag_id
     * @return mixed
     */
    public function tagDelete($tag_id)
    {
        $sql = "
          DELETE FROM _db_core_tags
		  WHERE       tag_id = :tag_id
        ";

        return $this->db->queryAffects($sql, array('tag_id' => $tag_id));
    }
}
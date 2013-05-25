<?php
class ReadMeModel extends PHPDS_model
{
    public function test ()
    {
        $array1 = array(
            'user_id' => 6,
            'user_display_name',
            'user_name',
            'user_password',
            'user_email',
            'user_role',
            'date_registered',
            'language',
            'timezone', 'region'
        );

        $array2 = array(
            'user_id' => 5,
            'user_display_name'
        );

        print $this->select($array2, '_db_core_users');
        print_r ($this->select($array1, '_db_core_users'));

        $array3 = array(
            'user_id' => 6,
            'user_display_name' => 'Thomas Edison',
            'user_name' => 'thommy',
            'user_password' => '63a9f0ea7bb98050796b649e85481845',
            'user_email' => 'thommy2@phpdevshell.org',
            'user_role' => 8,
            'date_registered' => 1362575916,
            'language' => 'en',
            'timezone' => 'UTC', 'region' => 'US'
        );

        if ($this->update($array3 ,'_db_core_users')) {
            $this->template->ok('This fucking things was updated!');
        }

        $array4 = array(
            'user_id'           => null,
            'user_display_name' => 'Thomas Edison_' . rand(9,9999),
            'user_name'         => 'thommy_' . rand(9, 9999),
            'user_password'     => '63a9f0ea7bb98050796b649e85481845',
            'user_email'        => 'thommy2@phpdevshell.org_' . rand(9, 9999),
            'user_role'         => 8,
            'date_registered'   => 1362575916,
            'language'          => 'en',
            'timezone'          => 'UTC', 'region' => 'US'
        );

        if ($this->insert($array4, '_db_core_users')) {
            $this->template->ok('This fucking things was inserted!');
        }

        $array5 = array('user_id' => 5);
        if ($this->delete($array5, '_db_core_users')) {
            $this->template->ok('This fucking things was deleted!');
        }

        $array6 = array(
            'user_id'           => 6,
            'user_display_name' => 'Thomas Edison',
            'user_name'         => 'thommy22',
            'user_password'     => '63a9f0ea7bb98050796b649e85481845',
            'user_email'        => 'thommy22@phpdevshell.org',
            'user_role'         => 8,
            'date_registered'   => 1362575916,
            'language'          => 'en',
            'timezone'          => 'UTC', 'region' => 'US'
        );

        if ($this->upsert($array6, '_db_core_users')) {
            $this->template->ok('This fucking things was upserted!');
        }

    }
}

return 'ReadMeModel';
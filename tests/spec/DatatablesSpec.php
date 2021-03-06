<?php
namespace spec\Ozdemir\Datatables;

use Ozdemir\Datatables\DB\MySQL;
use Ozdemir\Datatables\DB\SQLite;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class DatatablesSpec extends ObjectBehavior {

    function let()
    {
        /// SQLite Testing
        $app_path = realpath(dirname(__FILE__) . '/../../');
        $sqlconfig = $app_path . '/db/sqlite-sakila.sqlite';
        /// Mysql Testing
        $mysqlconfig = ['host' => 'localhost', 'port' => '3306', 'username' => 'homestead', 'password' => 'secret', 'database' => 'sakila'];

        $db = new SQLite($sqlconfig);
        //$db = new MySQL($mysqlconfig);

        $this->beConstructedWith($db);
    }

    public function getMatchers()
    {
        return [
            'haveColumns' => function ($subject, $key)
            {
                return (array_keys($subject) === $key);
            }
        ];
    }

    public function it_returns_record_counts()
    {
        $this->query("Select film_id as fid, title, description from film where film_id > 655");
        $datatables = $this->generate(false);
        $datatables['recordsTotal']->shouldReturn(345);
        $datatables['recordsFiltered']->shouldReturn(345);
    }

    public function it_returns_data_from_a_basic_sql()
    {
        $this->query("Select film_id as fid, title, description from film");

        $data = $this->generate(false)['data'][0];

        $data['fid']->shouldReturn("1");
        $data['title']->shouldReturn("ACADEMY DINOSAUR");
        $data['description']->shouldContain('A Epic Drama of a');
    }

    public function it_sets_column_names_from_aliases()
    {
        $this->query("Select
                  film_id as fid,
                  title,
                  'description' as info,
                  release_year 'r_year',
                  film.rental_rate,
                  film.length as mins
            from film");
        $this->get('columns')->shouldReturn(['fid', 'title', 'info', 'r_year', 'rental_rate', 'mins']);
    }

    public function it_returns_modified_data_via_closure_function()
    {
        $this->query("Select film_id as fid, title, description from film");

        $this->edit('title', function ($data)
        {
            return strtolower($data['title']);
        });

        $this->edit('description', function ($data)
        {
            return $this->customfunction($data['description']);
        });

        $data = $this->generate(false)['data']['0'];

        $data['title']->shouldReturn('academy dinosaur');
        $data['description']->shouldReturn('A Epic Dra...');
    }

    function customfunction($data)
    {
        return substr($data, 0, 10) . '...';
    }

    public function it_returns_data_from_a_more_complex_sql()
    {
        $this->query("Select 
          (category.name) as category_name, 
          sum(length) as total_length 
        from film 
        left join film_category on film_category.film_id = film.film_id
        left join category on film_category.category_id = category.category_id
        group by category.category_id");

        $this->edit('total_length', function ($data){
            return $data['total_length'] . ' minutes';
        });
        $data = $this->generate(false)['data'][4];

        $data['category_name']->shouldReturn("Comedy");
        $data['total_length']->shouldReturn("6718 minutes");
        $data->shouldHaveColumns(['category_name', 'total_length']);
    }

    public function it_returns_column_names_from_query_that_includes_a_subquery_in_select_statement()
    {
        $dt = $this->query( "SELECT column_name,
            (SELECT group_concat(cp.GRANTEE)
            FROM COLUMN_PRIVILEGES cp
            WHERE cp.TABLE_SCHEMA = COLUMNS.TABLE_SCHEMA
            AND cp.TABLE_NAME = COLUMNS.TABLE_NAME
            AND cp.COLUMN_NAME = COLUMNS.COLUMN_NAME)
            privs
            FROM COLUMNS
            WHERE table_schema = 'mysql' AND table_name = 'user';");

        $dt->get('columns')->shouldReturn(['column_name', 'privs']);
    }

    public function it_returns_column_names_from_query_that_includes_a_subquery_in_where_statement()
    {
        $dt = $this->query( "SELECT column_name
            FROM COLUMNS
            WHERE table_schema = 'mysql' AND table_name = 'user'
            and (SELECT group_concat(cp.GRANTEE)
            FROM COLUMN_PRIVILEGES cp
            WHERE cp.TABLE_SCHEMA = COLUMNS.TABLE_SCHEMA
            AND cp.TABLE_NAME = COLUMNS.TABLE_NAME
            AND cp.COLUMN_NAME = COLUMNS.COLUMN_NAME) is not null;");

        $dt->get('columns')->shouldReturn(['column_name']);
    }
}
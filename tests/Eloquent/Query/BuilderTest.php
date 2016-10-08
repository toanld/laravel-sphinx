<?php
namespace Fobia\Database\SphinxConnection\Test\Eloquent\Query;

use Fobia\Database\SphinxConnection\Eloquent\Query\Builder;
use Fobia\Database\SphinxConnection\Test\TestCase;
use Foolz\SphinxQL\Match;

class BuilderTest extends TestCase
{
    /**
     * @var Builder
     */
    protected $q;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    public function setUp()
    {
        parent::setUp();
        $this->setUpDatabase();

        $this->q = $this->makeQ();
    }

    /**
     * @return Builder
     */
    protected function makeQ()
    {
        return $this->db->table('rt');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    public function tearDown()
    {
        $this->db->statement("TRUNCATE RTINDEX rt");
        parent::tearDown();
    }

    /**
     * @covers \Fobia\Database\SphinxConnection\Eloquent\Query\Builder::toSql
     */
    public function testToSql()
    {
        $s = $this->q->select()->toSql();
        $this->assertQuery('select * FROM rt', $s);
    }

    public function test_intType()
    {
        $q = $this->q->where('id', 1);
        $this->assertQuery("select * FROM rt where id = 1", $q);
    }

    public function test_stringType()
    {
        $q = $this->q->where('id', '1');
        $this->assertQuery("select * FROM rt where id = '1'", $q);
    }

    public function test_floatType()
    {
        $q = $this->q->where('id', 1.1);
        $this->assertQuery("select * FROM rt where id = 1.100000", $q);
    }

    public function test_mvaType()
    {
        $q = $this->q->where('id', [1, 2, 3]);
        $this->assertQuery("select * FROM rt where id = (1, 2, 3)", $q);
    }


    public function test_boolType()
    {
        $q = $this->q->where('id', true);
        $this->assertQuery("select * FROM rt where id = 1", $q);
    }

    /**
     * @covers \Fobia\Database\SphinxConnection\Eloquent\Query\Builder::replace
     */
    public function testInsert()
    {
        $r = $this->q->insert([
            'id' => 1,
            'name' => 'name',
            'tags' => [1, 2, 3],
            'gid' => 1,
            'greal' => 1.5,
            'gbool' => true,
        ]);

        $this->assertEquals(1, $r);
        $this->assertQuery("insert into rt (id, name, tags, gid, greal, gbool) values (1, 'name', (1, 2, 3), 1, 1.5, 1))");
    }

    public function testSelect()
    {
        $q = $this->makeQ()->select('id');
        $this->assertQuery("select id FROM rt", $q);

        $q = $this->makeQ()->select('id', 'name');
        $this->assertQuery("select id, name FROM rt", $q);
    }

    /**
     * @covers \Fobia\Database\SphinxConnection\Eloquent\Query\Builder::replace
     */
    public function testReplace()
    {
        $r = $this->q->replace([
            'id' => 1,
            'name' => 'name',
            'tags' => [4, 5, 6],
            'gid' => 2,
            'greal' => 2.5,
            'gbool' => true,
        ]);

        $this->assertEquals(1, $r);
        $this->assertQuery("replace into rt (id, name, tags, gid, greal, gbool) values (1, 'name', (1, 2, 3), 1, 1.5, 1))");
    }

    /**
     * @covers \Fobia\Database\SphinxConnection\Eloquent\Query\Builder::update
     * @todo   Implement testUpdate().
     */
    public function testUpdate()
    {
        $this->makeQ()->replace([
            'id' => 1,
        ]);
        $r = $this->q->where('id', 1)->update([
            'gid' => 7,
            'greal' => 8.6,
            'tags' => [1, 2, 3, 4, 5],
            'gbool' => true,
        ]);
        $this->assertEquals(1, $r);
    }

    /**
     * @covers \Fobia\Database\SphinxConnection\Eloquent\Query\Builder::whereMulti
     * @todo   Implement testWhereMulti().
     */
    public function testWhereMulti()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @covers \Fobia\Database\SphinxConnection\Eloquent\Query\Builder::option
     */
    public function testOption()
    {
        $q = $this->q->option('ranker', 'bm25');
        $this->assertQuery('select * from rt OPTION ranker = bm25', $q);

        $q->option('max_matches', '3000');
        $this->assertQuery('select * from rt OPTION ranker = bm25,max_matches=3000', $q);

        $q->option('field_weights', '(title=10, body=3)');
        $this->assertQuery('select * from rt OPTION ranker = bm25,max_matches=3000, field_weights=(title=10, body=3)',
            $q);

        $q->option('agent_query_timeout', '10000');
        $this->assertQuery('select * from rt OPTION ranker = bm25,max_matches=3000, field_weights=(title=10, body=3) , agent_query_timeout=10000',
            $q);
        $q->get();
    }

    /**
     * @covers \Fobia\Database\SphinxConnection\Eloquent\Query\Builder::option
     */
    public function testOption2()
    {
        $q = $this->q->option('field_weights', ['title' => 10, 'body' => 3]);
        $this->assertQuery('select * from rt OPTION field_weights=(title=10, body=3)', $q);

        $q->option('comment', 'my comment');
        $this->assertQuery('select * from rt OPTION field_weights=(title=10, body=3), comment=\'my comment\'', $q);
    }

    /**
     * @covers \Fobia\Database\SphinxConnection\Eloquent\Query\Builder::withinGroupOrderBy
     */
    public function testWithinGroupOrderBy()
    {
        $q = $this->q->select('id');
        $q = $q->withinGroupOrderBy('name');
        $this->assertQuery('SELECT id FROM rt WITHIN GROUP ORDER BY name ASC', $q);

        $q = $q->withinGroupOrderBy('id', 'desc');
        $this->assertQuery('SELECT id FROM rt WITHIN GROUP ORDER BY name ASC, id DESC', $q);
    }

    /**
     * @covers \Fobia\Database\SphinxConnection\Eloquent\Query\Builder::withinGroupOrderBy
     * @expectedException \RuntimeException
     */
    public function testWithinGroupOrderByException()
    {
        $q = $this->q->select('id');
        $q = $q->withinGroupOrderBy('name', 'a');
    }

    /**
     * @covers \Fobia\Database\SphinxConnection\Eloquent\Query\Builder::match
     */
    public function testMatch()
    {
        $q = $this->q->match('text match');
        $this->assertQuery("select * FROM rt WHERE MATCH('(@text match)')", $q);

        $q = $this->makeQ()->match(['name'], 'match');
        $this->assertQuery("select * FROM rt WHERE MATCH('(@(name) match)')", $q);

        $q = $this->makeQ()->match(['name', 'content'], 'match');
        $this->assertQuery("select * FROM rt WHERE MATCH('(@(name,content) match)')", $q);

        $q = $this->makeQ()->match(function(Match $m) {
            $m->match('match');
        });
        $this->assertQuery("select * FROM rt WHERE MATCH('(match)')", $q);

        $q = $this->makeQ()->match(function(Match $m) {
            $m->field('name');
            $m->match('match');
        });
        $this->assertQuery("select * FROM rt WHERE MATCH('(@name match)')", $q);
    }

    /**
     * @covers \Fobia\Database\SphinxConnection\Eloquent\Query\Builder::facet
     * @todo   Implement testFacet().
     */
    public function testFacet()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @covers \Fobia\Database\SphinxConnection\Eloquent\Query\Builder::filterParamsUint
     * @todo   Implement testFilterParamsUint().
     */
    public function testFilterParamsUint()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

}

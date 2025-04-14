<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Collection;
use MongoDB\BSON\ObjectId;

class TestCollection extends Collection {
    protected $collectionName = 'test_collection';
}

class CollectionTest extends TestCase {
    private $collection;
    private $testData;

    protected function setUp(): void {
        parent::setUp();
        $this->collection = new TestCollection();
        
        // Test data
        $this->testData = [
            'name' => 'Test Item',
            'description' => 'Test Description',
            'status' => 'active'
        ];
    }

    protected function tearDown(): void {
        // Clean up test data
        $this->collection->delete($this->testData['_id'] ?? null);
        parent::tearDown();
    }

    public function testCreate() {
        $result = $this->collection->create($this->testData);
        
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['id']);
        $this->assertInstanceOf(ObjectId::class, new ObjectId($result['id']));
    }

    public function testRead() {
        // Create test data first
        $created = $this->collection->create($this->testData);
        $this->assertTrue($created['success']);
        
        // Test reading single item
        $item = $this->collection->read($created['id']);
        $this->assertNotNull($item);
        $this->assertEquals($this->testData['name'], $item['name']);
        
        // Test reading all items
        $items = $this->collection->read();
        $this->assertIsArray($items);
        $this->assertGreaterThan(0, count($items));
    }

    public function testUpdate() {
        // Create test data
        $created = $this->collection->create($this->testData);
        $this->assertTrue($created['success']);
        
        // Update data
        $updateData = ['name' => 'Updated Name'];
        $result = $this->collection->update($created['id'], $updateData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['modified']);
        
        // Verify update
        $updated = $this->collection->read($created['id']);
        $this->assertEquals('Updated Name', $updated['name']);
    }

    public function testDelete() {
        // Create test data
        $created = $this->collection->create($this->testData);
        $this->assertTrue($created['success']);
        
        // Delete data
        $result = $this->collection->delete($created['id']);
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['deleted']);
        
        // Verify deletion
        $deleted = $this->collection->read($created['id']);
        $this->assertNull($deleted);
    }

    public function testFind() {
        // Create test data
        $this->collection->create($this->testData);
        
        // Test find with filter
        $results = $this->collection->find(['status' => 'active']);
        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));
        $this->assertEquals('active', $results[0]['status']);
    }

    public function testFindOne() {
        // Create test data
        $created = $this->collection->create($this->testData);
        
        // Test findOne
        $result = $this->collection->findOne(['_id' => new ObjectId($created['id'])]);
        $this->assertNotNull($result);
        $this->assertEquals($this->testData['name'], $result['name']);
    }

    public function testCount() {
        // Create test data
        $this->collection->create($this->testData);
        
        // Test count
        $count = $this->collection->count(['status' => 'active']);
        $this->assertGreaterThan(0, $count);
    }

    public function testCreateIndex() {
        $result = $this->collection->createIndex(['name' => 1], ['unique' => true]);
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['indexName']);
    }
} 
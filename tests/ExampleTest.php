<?php

namespace Tests;

class ExampleTest extends TestCase
{
    protected $collection = "test_documents";

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanCollection($this->collection);
    }

    public function testCreateAndRetrieveDocument()
    {
        // Create a test document
        $doc = [
            "name" => "Test Document",
            "type" => "example",
            "created_at" => new \MongoDB\BSON\UTCDateTime()
        ];

        $this->createTestDocument($this->collection, $doc);

        // Assert document exists
        $this->assertDocumentExists($this->collection, [
            "name" => "Test Document",
            "type" => "example"
        ]);

        // Retrieve the document
        $db = $this->getDb();
        $result = $db->selectCollection($this->collection)->findOne([
            "name" => "Test Document"
        ]);

        $this->assertNotNull($result);
        $this->assertEquals("Test Document", $result->name);
        $this->assertEquals("example", $result->type);
    }

    public function testDocumentNotExists()
    {
        $this->assertDocumentNotExists($this->collection, [
            "name" => "Non Existent Document"
        ]);
    }
} 
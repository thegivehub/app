<?php
use PHPUnit\Framework\TestCase;

class KycComplianceReportTest extends TestCase {
    private $controller;
    private $jumioService;
    private $admin;
    private $adminToken;

    protected function setUp(): void {
        parent::setUp();
        $this->jumioService = $this->createMock(JumioService::class);
        $this->controller = new KycController();
        $ref = new ReflectionClass($this->controller);
        $prop = $ref->getProperty('jumioService');
        $prop->setAccessible(true);
        $prop->setValue($this->controller, $this->jumioService);

        $this->admin = createTestUser([
            'roles' => ['user','admin'],
            'email' => 'compliance@example.com',
            'username' => 'compliance'
        ]);
        $this->adminToken = generateTestToken($this->admin['_id']);
    }

    protected function tearDown(): void {
        $db = new Database();
        $db->getCollection('users')->deleteMany(['_id' => new MongoDB\BSON\ObjectId($this->admin['_id'])]);
        parent::tearDown();
    }

    public function testGenerateComplianceReport() {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->adminToken;

        $this->jumioService->expects($this->once())
            ->method('generateComplianceReport')
            ->with($this->isType('array'))
            ->willReturn([
                'success' => true,
                'statusCounts' => ['APPROVED' => 1],
                'riskCounts' => ['high' => 0, 'medium' => 1, 'low' => 0],
                'highRiskUsers' => []
            ]);

        $result = $this->controller->generateComplianceReport();
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('statusCounts', $result);
        $this->assertArrayHasKey('riskCounts', $result);
    }
}

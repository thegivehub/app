<?php
namespace Storage;

/**
 * StorageInterface
 * 
 * Defines the contract that all storage adapters must implement.
 * This ensures consistent CRUD operations across different storage types.
 */
interface StorageInterface {
    /**
     * Create a new record
     * 
     * @param string $collection The collection/table/container name
     * @param array $data The data to store
     * @return array Result with success status and ID
     */
    public function create(string $collection, array $data): array;

    /**
     * Read one or many records
     * 
     * @param string $collection The collection/table/container name
     * @param mixed $id Optional ID to fetch specific record
     * @param array $options Query options (limit, offset, etc)
     * @return array|null The record(s) or null if not found
     */
    public function read(string $collection, $id = null, array $options = []): ?array;

    /**
     * Update a record
     * 
     * @param string $collection The collection/table/container name
     * @param mixed $id The record identifier
     * @param array $data The data to update
     * @return array Result with success status
     */
    public function update(string $collection, $id, array $data): array;

    /**
     * Delete a record
     * 
     * @param string $collection The collection/table/container name
     * @param mixed $id The record identifier
     * @return array Result with success status
     */
    public function delete(string $collection, $id): array;

    /**
     * Find records by criteria
     * 
     * @param string $collection The collection/table/container name
     * @param array $criteria Search criteria
     * @param array $options Query options
     * @return array The matching records
     */
    public function find(string $collection, array $criteria = [], array $options = []): array;

    /**
     * Count records matching criteria
     * 
     * @param string $collection The collection/table/container name
     * @param array $criteria Search criteria
     * @return int Number of matching records
     */
    public function count(string $collection, array $criteria = []): int;

    /**
     * Get the native connection/client
     * Allows access to storage-specific features when needed
     * 
     * @return mixed The native connection object
     */
    public function getNativeConnection();
} 
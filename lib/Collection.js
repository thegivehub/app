class Collection {
  constructor() {
    this.collectionName = null;  // override in subclasses
    // TODO: wire up your database connector here
  }

  async create(data) { /* ... */ }
  async read(id = null) { /* ... */ }
  async update(id, data) { /* ... */ }
  async delete(id) { /* ... */ }
}

module.exports = Collection;

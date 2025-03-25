// Admin Navigation Web Component
class AdminNavigation extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    
    // Component state
    this.state = {
      navLoaded: false,
      currentRoute: '',
      defaultPage: 'dashboard.html',
      navData: null
    };

    // Bind methods
    this.handleNavClick = this.handleNavClick.bind(this);
    this.findAndLoadNavItemByHash = this.findAndLoadNavItemByHash.bind(this);
  }

  connectedCallback() {
    // Initialize the component
    this.render();
    this.loadNavigation();
    this.handleInitialRoute();

    // Listen for hash changes
    window.addEventListener('hashchange', () => {
      const newHash = window.location.hash.substring(1);
      if (newHash) {
        this.findAndLoadNavItemByHash(newHash);
      }
    });
  }

  // Initial render of the component structure
  render() {
    // Apply base styles
    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
          font-family: 'Lexend', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .nav-sidebar {
          height: calc(100vh - 120px);
          overflow-y: auto;
          overflow-x: hidden;
          padding: 0;
        }
        
        .nav-sidebar::-webkit-scrollbar {
          width: 5px;
        }
        
        .nav-sidebar::-webkit-scrollbar-thumb {
          background-color: #d1d5db;
          border-radius: 10px;
        }
        
        .nav-sidebar::-webkit-scrollbar-track {
          background-color: #f3f4f6;
        }
        
        .nav-link {
          display: flex;
          align-items: center;
          padding: 0.75rem 1rem;
          color: #374151;
          text-decoration: none;
          transition: background-color 0.15s ease;
        }
        
        .nav-link:hover {
          background-color: #f3f4f6;
        }
        
        .nav-link.active {
          background-color: #eff6ff;
          color: #2563eb;
        }
        
        .nav-icon {
          margin-right: 0.75rem;
          width: 20px;
          text-align: center;
          font-size: 1rem;
        }
        
        .nav-treeview {
          padding-left: 1rem;
        }
        
        .nav-item.menu-open > .nav-treeview {
          display: block;
        }
        
        .nav-item {
          position: relative;
          list-style: none;
        }
        
        .right {
          position: absolute;
          right: 1rem;
          top: 50%;
          transform: translateY(-50%);
          transition: transform 0.2s ease;
        }
        
        .nav-item.menu-open > .nav-link .right {
          transform: translateY(-50%) rotate(-90deg);
        }
      </style>
      
      <div class="nav-sidebar">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false" id="sidemenu">
          <!-- Navigation items will be inserted here -->
          <slot></slot>
        </ul>
      </div>
    `;
    
    // Create a link element for Font Awesome
    const fontAwesomeLink = document.createElement('link');
    fontAwesomeLink.rel = 'stylesheet';
    fontAwesomeLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
    
    // Append the link to the document head
    document.head.appendChild(fontAwesomeLink);
  }

  // Load navigation from JSON file
  async loadNavigation() {
    try {
      const navUrl = this.getAttribute('data-nav-url') || 'nav/nav.json';
      const response = await fetch(navUrl);
      
      if (!response.ok) {
        throw new Error(`Failed to load navigation: ${response.statusText}`);
      }
      
      this.state.navData = await response.json();
      this.renderNavigation();
      this.state.navLoaded = true;
      
      // Dispatch event that navigation is loaded
      this.dispatchEvent(new CustomEvent('navigation-loaded', {
        detail: { navData: this.state.navData }
      }));
    } catch (error) {
      console.error('Error loading navigation:', error);
      this.renderFallbackNavigation();
    }
  }

  // Render navigation items
  renderNavigation() {
    if (!this.state.navData) return;
    
    const sidemenu = this.shadowRoot.getElementById('sidemenu');
    sidemenu.innerHTML = '';
    
    // Render sidebar items
    if (this.state.navData.sidemenu) {
      this.state.navData.sidemenu.forEach(item => {
        sidemenu.appendChild(this.createNavItem(item));
      });
    }
    
    // Dispatch event that navigation is rendered
    this.dispatchEvent(new CustomEvent('navigation-rendered'));
  }

  // Create navigation item element
  createNavItem(item) {
    const navItem = document.createElement('li');
    navItem.className = 'nav-item';
    
    if (item.children && item.children.length > 0) {
      navItem.classList.add('has-treeview');
    }
    
    const link = document.createElement('a');
    link.href = item.link || '#';
    link.className = 'nav-link';
    link.setAttribute('data-page', item.link || '');
    link.addEventListener('click', (e) => this.handleNavClick(e, link));
    
    // Handle icon based on available properties
    if (item.iconUrl) {
      // Use the iconUrl if available (preferred approach)
      if (item.iconUrl.startsWith('data:image/svg')) {
        // It's an SVG data URL - create an SVG element
        const iconWrapper = document.createElement('span');
        iconWrapper.className = 'nav-icon';
        
        // Instead of attempting to decode, just use the data URL directly in an img tag
        const imgIcon = document.createElement('img');
        imgIcon.src = item.iconUrl;
        imgIcon.style.width = '1em';
        imgIcon.style.height = '1em';
        iconWrapper.appendChild(imgIcon);
        
        link.appendChild(iconWrapper);
      } else {
        // It's a regular image URL
        const imgIcon = document.createElement('img');
        imgIcon.src = item.iconUrl;
        imgIcon.className = 'nav-icon';
        imgIcon.style.width = '20px';
        imgIcon.style.height = '20px';
        link.appendChild(imgIcon);
      }
    } else if (item.icon) {
      // Fallback to icon class if iconUrl is not provided
      if (item.icon.match(/\.(gif|png|jpg|svg)/)) {
        // It's an image path
        const imgIcon = document.createElement('img');
        imgIcon.src = item.icon;
        imgIcon.className = 'nav-icon';
        imgIcon.style.width = '20px';
        imgIcon.style.height = '20px';
        link.appendChild(imgIcon);
      } else {
        // It's a FontAwesome class or similar
        const icon = document.createElement('i');
        icon.className = `nav-icon ${item.icon}`;
        link.appendChild(icon);
      }
    } else {
      // Default icon if none provided
      const icon = document.createElement('i');
      icon.className = 'nav-icon fas fa-circle';
      link.appendChild(icon);
    }
    
    // Add title
    const text = document.createElement('span');
    text.textContent = item.title;
    text.style.marginLeft = '0.5rem';
    link.appendChild(text);
    
    // Add dropdown arrow if has children
    if (item.children && item.children.length > 0) {
      const arrow = document.createElement('i');
      arrow.className = 'right fas fa-angle-left';
      link.appendChild(arrow);
    }
    
    navItem.appendChild(link);
    
    // Add children if they exist
    if (item.children && item.children.length > 0) {
      const treeview = document.createElement('ul');
      treeview.className = 'nav nav-treeview';
      
      item.children.forEach(child => {
        treeview.appendChild(this.createNavItem(child));
      });
      
      navItem.appendChild(treeview);
    }
    
    return navItem;
  }

  // Render fallback navigation if loading fails
  renderFallbackNavigation() {
    const sidemenu = this.shadowRoot.getElementById('sidemenu');
    sidemenu.innerHTML = `
      <li class="nav-item">
        <a href="dashboard.html" class="nav-link" data-page="dashboard.html">
          <i class="nav-icon fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="campaigns.html" class="nav-link" data-page="campaigns.html">
          <i class="nav-icon fas fa-chart-line"></i>
          <span>Campaign Review</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="users.html" class="nav-link" data-page="users.html">
          <i class="nav-icon fas fa-users"></i>
          <span>User Management</span>
        </a>
      </li>
       <li class="nav-item">
        <a href="reports.html" class="nav-link" data-page="reports.html">
          <i class="nav-icon fas fa-file-lines"></i>
          <span>Reports</span>
        </a>
      </li>
    `;
    
    // Add event listeners to the fallback navigation
    sidemenu.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', (e) => this.handleNavClick(e, link));
    });
  }

  // Handle navigation item click
  handleNavClick(event, element) {
    event.preventDefault();
    
    const page = element.getAttribute('data-page');
    
    // Don't do anything if it's a parent menu without a link
    if (!page || page === '#') {
      // Toggle submenu if it's a parent item
      const listItem = element.parentElement;
      if (listItem.classList.contains('has-treeview')) {
        listItem.classList.toggle('menu-open');
      }
      return;
    }
    
    // Update active state
    this.shadowRoot.querySelectorAll('.nav-link').forEach(link => {
      link.classList.remove('active');
    });
    
    element.classList.add('active');
    
    // Load the page - dispatch event for parent to handle
    this.dispatchEvent(new CustomEvent('navigation-click', {
      detail: { page: page }
    }));
    
    // Update current route
    this.state.currentRoute = page;
    
    // Update browser URL
    this.updateBrowserUrl(page);
  }

  // Update browser URL
  updateBrowserUrl(page) {
    // Extract the page name without extension to use as hash
    const pageName = page.replace('.html', '');
    
    // Update both query param and hash for better compatibility
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    url.hash = pageName;
    window.history.pushState({}, '', url);
  }

  // Handle initial route from URL
  handleInitialRoute() {
    // Check if there's a hash in the URL
    const hash = window.location.hash.substring(1);
    
    // Check if there's a route in the URL query params
    const urlParams = new URLSearchParams(window.location.search);
    const route = urlParams.get('page');
    
    // Wait for navigation to load
    if (hash) {
      // We'll process this after navigation is loaded
      this.addEventListener('navigation-rendered', () => {
        this.findAndLoadNavItemByHash(hash);
      }, { once: true });
    } else if (route) {
      this.state.currentRoute = route;
      
      // Dispatch event to load the page
      this.dispatchEvent(new CustomEvent('navigation-click', {
        detail: { page: route }
      }));
      
      // Update active state when navigation is rendered
      this.addEventListener('navigation-rendered', () => {
        this.updateActiveNavState(route);
      }, { once: true });
    } else {
      // Load default page
      this.dispatchEvent(new CustomEvent('navigation-click', {
        detail: { page: this.state.defaultPage }
      }));
    }
  }

  // Find and load a nav item based on hash value
  findAndLoadNavItemByHash(hash) {
    // Wait until navigation is loaded
    if (!this.state.navLoaded) {
      this.addEventListener('navigation-rendered', () => {
        this.findAndLoadNavItemByHash(hash);
      }, { once: true });
      return;
    }
    
    // Normalize the hash value for comparison
    const normalizedHash = hash.toLowerCase().replace(/\W/g, '');
    
    // Find all nav links
    const navLinks = this.shadowRoot.querySelectorAll('.nav-link');
    let matchedLink = null;
    
    // First try to find an exact match for the page URL
    for (let i = 0; i < navLinks.length; i++) {
      const link = navLinks[i];
      const page = link.getAttribute('data-page');
      
      // Skip links without a page attribute or with '#'
      if (!page || page === '#') continue;
      
      // Check if the link matches the hash exactly
      if (page === hash || page === hash + '.html') {
        matchedLink = link;
        break;
      }
    }
    
    // If no exact match, try to find a match by title
    if (!matchedLink) {
      for (let i = 0; i < navLinks.length; i++) {
        const link = navLinks[i];
        const page = link.getAttribute('data-page');
        
        // Skip links without a page attribute or with '#'
        if (!page || page === '#') continue;
        
        // Get the title text and normalize it
        const titleText = link.textContent.trim().toLowerCase().replace(/\W/g, '');
        
        if (titleText === normalizedHash) {
          matchedLink = link;
          break;
        }
      }
    }
    
    // If a match was found, load the page and update the active state
    if (matchedLink) {
      const page = matchedLink.getAttribute('data-page');
      
      // Dispatch event to load the page
      this.dispatchEvent(new CustomEvent('navigation-click', {
        detail: { page: page }
      }));
      
      this.updateActiveNavState(page);
      
      // Scroll the matched link into view in the sidebar
      matchedLink.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
      console.warn(`No navigation item found for hash: ${hash}`);
      // Fall back to the default page
      this.dispatchEvent(new CustomEvent('navigation-click', {
        detail: { page: this.state.defaultPage }
      }));
    }
  }

  // Update the active state in the navigation
  updateActiveNavState(route) {
    const navLink = this.shadowRoot.querySelector(`.nav-link[data-page="${route}"]`);
    
    if (navLink) {
      // Remove active class from all nav links
      this.shadowRoot.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
      });
      
      // Add active class to the matched link
      navLink.classList.add('active');
      
      // Open parent menu if needed
      let parent = navLink.parentElement;
      while (parent && parent.classList.contains('nav-item')) {
        if (parent.classList.contains('has-treeview')) {
          parent.classList.add('menu-open');
        }
        parent = parent.parentElement;
      }
    }
  }

  // Define observed attributes
  static get observedAttributes() {
    return ['data-nav-url', 'data-default-page'];
  }

  // Handle attribute changes
  attributeChangedCallback(name, oldValue, newValue) {
    if (name === 'data-nav-url' && oldValue !== newValue) {
      this.loadNavigation();
    }
    
    if (name === 'data-default-page' && oldValue !== newValue) {
      this.state.defaultPage = newValue;
    }
  }
}

// Register the custom element
customElements.define('admin-navigation', AdminNavigation);

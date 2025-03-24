// Admin Reports JavaScript
document.addEventListener('DOMContentLoaded', () => {
    // Initialize the admin reports functionality
    AdminReports.init();
});

// Main admin module for reports
const AdminReports = {
    // App state
    state: {
        currentReport: null,
        dateRange: {
            start: null,
            end: null,
            preset: '30' // Default to 30 days
        },
        charts: {},
        exportData: null
    },

    // Configuration
    config: {
        apiBase: '/api.php/admin/reports',
        reports: [
            'campaign-performance',
            'donor-activity',
            'financial-summary',
            'user-growth',
            'category-analysis',
            'geographic-distribution'
        ]
    },

    // Initialize the module
    init() {
        this.setupEventListeners();
        this.setupDateRange();
        this.initializeCharts();
    },
    
    // Set up event listeners
    setupEventListeners() {
        // View report buttons
        document.querySelectorAll('.view-report-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const reportCard = e.target.closest('.report-card');
                if (reportCard) {
                    const reportType = reportCard.dataset.report;
                    this.showReport(reportType);
                }
            });
        });

        // Export report buttons in report cards
        document.querySelectorAll('.export-report-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const reportCard = e.target.closest('.report-card');
                if (reportCard) {
                    const reportType = reportCard.dataset.report;
                    this.showExportModal(reportType);
                }
            });
        });

        // Back to reports buttons
        document.querySelectorAll('.back-to-reports').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.showReportsList();
            });
        });

        // Export buttons in report views
        document.querySelectorAll('.export-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const reportView = e.target.closest('.report-view');
                const reportId = reportView ? reportView.id.replace('-report', '') : null;
                if (reportId) {
                    this.showExportModal(reportId);
                }
            });
        });

        // Print buttons
        document.querySelectorAll('.print-btn').forEach(button => {
            button.addEventListener('click', () => {
                window.print();
            });
        });

        // Date range preset buttons
        document.querySelectorAll('.preset-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                this.setDateRangePreset(e.target.dataset.range);
            });
        });

        // Date inputs
        document.getElementById('start-date').addEventListener('change', (e) => {
            this.state.dateRange.start = e.target.value;
            this.applyDateRange();
        });

        document.getElementById('end-date').addEventListener('change', (e) => {
            this.state.dateRange.end = e.target.value;
            this.applyDateRange();
        });

        // Export modal
        document.getElementById('close-export-modal').addEventListener('click', () => {
            this.hideExportModal();
        });

        document.getElementById('cancel-export').addEventListener('click', () => {
            this.hideExportModal();
        });

        document.getElementById('confirm-export').addEventListener('click', () => {
            this.exportReport();
        });

        // Remove filter chips
        document.querySelectorAll('.remove-filter').forEach(button => {
            button.addEventListener('click', (e) => {
                const filterChip = e.target.closest('.filter-chip');
                if (filterChip) {
                    filterChip.remove();
                    // In a real app, this would also update the filter state
                }
            });
        });

        // Refresh button
        document.getElementById('refresh-btn').addEventListener('click', () => {
            this.refreshData();
        });

        // Report-specific filter changes
        const filterSelects = document.querySelectorAll('.filter-select');
        filterSelects.forEach(select => {
            select.addEventListener('change', () => {
                if (this.state.currentReport) {
                    this.loadReportData(this.state.currentReport);
                }
            });
        });
    },

    // Set up initial date range
    setupDateRange() {
        // Set default date range to last 30 days
        const end = new Date();
        const start = new Date();
        start.setDate(start.getDate() - 30);

        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        this.state.dateRange.start = formatDate(start);
        this.state.dateRange.end = formatDate(end);

        // Update input values
        document.getElementById('start-date').value = this.state.dateRange.start;
        document.getElementById('end-date').value = this.state.dateRange.end;
    },

    // Set date range from preset
    setDateRangePreset(days) {
        // Update active state of preset buttons
        document.querySelectorAll('.preset-btn').forEach(button => {
            button.classList.toggle('active', button.dataset.range === days);
        });

        const end = new Date();
        const start = new Date();
        
        // Handle different presets
        if (days === 'year') {
            // This year
            start.setMonth(0);
            start.setDate(1);
        } else {
            // Last X days
            start.setDate(start.getDate() - parseInt(days));
        }

        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        this.state.dateRange.start = formatDate(start);
        this.state.dateRange.end = formatDate(end);
        this.state.dateRange.preset = days;

        // Update input values
        document.getElementById('start-date').value = this.state.dateRange.start;
        document.getElementById('end-date').value = this.state.dateRange.end;

        // Apply new date range
        this.applyDateRange();
    },

    // Apply the selected date range to current report
    applyDateRange() {
        // Update date range filter chip
        let rangeText = `${this.state.dateRange.start} to ${this.state.dateRange.end}`;
        
        // For presets, use more human-readable text
        if (this.state.dateRange.preset) {
            switch (this.state.dateRange.preset) {
                case '7':
                    rangeText = 'Last 7 days';
                    break;
                case '30':
                    rangeText = 'Last 30 days';
                    break;
                case '90':
                    rangeText = 'Last 90 days';
                    break;
                case 'year':
                    rangeText = 'This year';
                    break;
            }
        }

        document.querySelectorAll('.filter-chip').forEach(chip => {
            if (chip.textContent.includes('Date Range:')) {
                chip.childNodes[0].nodeValue = `Date Range: ${rangeText}`;
            }
        });

        // Reload data for the current report if one is active
        if (this.state.currentReport) {
            this.loadReportData(this.state.currentReport);
        }
    },

    // Show loading indicator
    showLoading(show = true) {
        const loadingOverlay = document.getElementById('loading-overlay');
        if (show) {
            loadingOverlay.classList.add('active');
        } else {
            loadingOverlay.classList.remove('active');
        }
    },

    // Show notification
    showNotification(message, type = 'success') {
        const notification = document.getElementById('notification');
        const notificationMessage = document.getElementById('notification-message');
        
        notification.className = 'notification';
        notification.classList.add(type);
        notification.classList.add('show');
        
        notificationMessage.textContent = message;
        
        // Hide notification after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    },

    // Show the reports list
    showReportsList() {
        // Hide all report views
        document.querySelectorAll('.report-view').forEach(view => {
            view.classList.remove('active');
        });
        
        // Show the reports list
        document.getElementById('reports-list').style.display = 'block';
        
        // Clear current report
        this.state.currentReport = null;
    },

    // Show a specific report
    showReport(reportType) {
        this.showLoading(true);
        
        // Hide the reports list
        document.getElementById('reports-list').style.display = 'none';
        
        // Hide all report views
        document.querySelectorAll('.report-view').forEach(view => {
            view.classList.remove('active');
        });
        
        // Show the selected report view
        const reportView = document.getElementById(`${reportType}-report`);
        if (reportView) {
            reportView.classList.add('active');
            
            // Set current report
            this.state.currentReport = reportType;
            
            // Load report data
            this.loadReportData(reportType);
        } else {
            this.showNotification('Report not found', 'error');
            this.showReportsList();
        }
        
        this.showLoading(false);
    },

    // Load data for a specific report
    async loadReportData(reportType) {
        try {
            this.showLoading(true);
            
            // In a real implementation, you would fetch data from your API
            // For this example, we'll simulate API calls with timeouts
            
            // Prepare API parameters
            const params = new URLSearchParams();
            params.append('type', reportType);
            params.append('start', this.state.dateRange.start);
            params.append('end', this.state.dateRange.end);
            
            // Add any additional filters specific to the report
            if (reportType === 'campaign-performance') {
                const statusFilter = document.getElementById('campaign-status-filter').value;
                const categoryFilter = document.getElementById('campaign-category-filter').value;
                
                if (statusFilter !== 'all') {
                    params.append('status', statusFilter);
                }
                
                if (categoryFilter !== 'all') {
                    params.append('category', categoryFilter);
                }
            } else if (reportType === 'donor-activity') {
                const donorTypeFilter = document.getElementById('donor-type-filter').value;
                const donorStatusFilter = document.getElementById('donor-status-filter').value;
                
                if (donorTypeFilter !== 'all') {
                    params.append('donorType', donorTypeFilter);
                }
                
                if (donorStatusFilter !== 'all') {
                    params.append('status', donorStatusFilter);
                }
            }
            
            // Simulate API call
            // In a real app, you would do something like:
            // const response = await fetch(`${this.config.apiBase}?${params.toString()}`, {
            //     headers: { 'Authorization': `Bearer ${localStorage.getItem('adminToken')}` }
            // });
            // const data = await response.json();
            
            // For demo purposes, we'll just use a timeout to simulate the API call
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // Update the chart for the report
            this.updateReportChart(reportType);
            
            this.showLoading(false);
        } catch (error) {
            console.error(`Error loading ${reportType} data:`, error);
            this.showNotification(`Failed to load report: ${error.message}`, 'error');
            this.showLoading(false);
        }
    },

    // Refresh data for the current view
    refreshData() {
        if (this.state.currentReport) {
            this.loadReportData(this.state.currentReport);
        } else {
            // Just show a success message for demo purposes
            this.showNotification('Data refreshed successfully');
        }
    },

    // Initialize charts for all reports
    initializeCharts() {
        // Campaign Performance Chart
        this.initCampaignPerformanceChart();
        
        // Donor Activity Chart
        this.initDonorActivityChart();
        
        // Financial Summary Chart
        this.initFinancialSummaryChart();
    },

    // Initialize Campaign Performance Chart
    initCampaignPerformanceChart() {
        const ctx = document.getElementById('campaign-performance-chart');
        if (!ctx) return;
        
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [
                    {
                        label: 'Goal Amount',
                        data: [12000, 15000, 10000, 8000, 18000, 14000],
                        backgroundColor: 'rgba(37, 99, 235, 0.2)',
                        borderColor: 'rgba(37, 99, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Raised Amount',
                        data: [10200, 12500, 9800, 6400, 12600, 8400],
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        this.state.charts['campaign-performance'] = chart;
    },

    // Initialize Donor Activity Chart
    initDonorActivityChart() {
        const ctx = document.getElementById('donor-activity-chart');
        if (!ctx) return;
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [
                    {
                        label: 'One-time Donors',
                        data: [85, 72, 86, 81, 84, 90],
                        borderColor: 'rgba(37, 99, 235, 1)',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Recurring Donors',
                        data: [25, 29, 32, 36, 40, 45],
                        borderColor: 'rgba(16, 185, 129, 1)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
        
        this.state.charts['donor-activity'] = chart;
    },

    // Initialize Financial Summary Chart
    initFinancialSummaryChart() {
        const ctx = document.getElementById('financial-summary-chart');
        if (!ctx) return;
        
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Nov', 'Dec', 'Jan', 'Feb', 'Mar'],
                datasets: [
                    {
                        label: 'Total Donations',
                        data: [36428, 58972, 41686, 38524, 45632],
                        backgroundColor: 'rgba(37, 99, 235, 0.7)',
                        order: 1
                    },
                    {
                        label: 'Platform Fees',
                        data: [1821, 2949, 2084, 1926, 2282],
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        order: 2
                    },
                    {
                        label: 'Growth',
                        data: [2.1, 23.8, 5.4, 8.7, 15.2],
                        type: 'line',
                        borderColor: 'rgba(245, 158, 11, 1)',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        yAxisID: 'percentage',
                        order: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    },
                    percentage: {
                        position: 'right',
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.yAxisID === 'percentage') {
                                    return context.dataset.label + ': ' + context.parsed.y + '%';
                                }
                                return context.dataset.label + ': $' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        this.state.charts['financial-summary'] = chart;
    },

    // Update chart for a specific report
    updateReportChart(reportType) {
        const chart = this.state.charts[reportType];
        if (!chart) return;
        
        // In a real app, this would update with actual data from the API
        // For this demo, we'll just randomize the data a bit
        
        const getData = () => {
            return Array.from({length: 6}, () => Math.floor(Math.random() * 20000) + 5000);
        };
        
        if (reportType === 'campaign-performance') {
            chart.data.datasets[0].data = getData();
            chart.data.datasets[1].data = getData().map(val => val * 0.8); // Raised is generally less than goal
        } else if (reportType === 'donor-activity') {
            chart.data.datasets[0].data = Array.from({length: 6}, () => Math.floor(Math.random() * 50) + 50);
            chart.data.datasets[1].data = Array.from({length: 6}, () => Math.floor(Math.random() * 30) + 20);
        } else if (reportType === 'financial-summary') {
            const donations = getData();
            chart.data.datasets[0].data = donations;
            chart.data.datasets[1].data = donations.map(val => val * 0.05); // 5% platform fee
            chart.data.datasets[2].data = Array.from({length: 5}, () => Math.floor(Math.random() * 25) + 1);
        }
        
        chart.update();
    },

    // Show export modal
    showExportModal(reportType) {
        const modal = document.getElementById('export-modal');
        modal.classList.add('active');
        
        // Store the report type for export
        this.state.exportData = reportType;
    },

    // Hide export modal
    hideExportModal() {
        const modal = document.getElementById('export-modal');
        modal.classList.remove('active');
    },

    // Export the report
    exportReport() {
        const reportType = this.state.exportData;
        const format = document.getElementById('export-format').value;
        const includeSummary = document.getElementById('include-summary').checked;
        const includeCharts = document.getElementById('include-charts').checked;
        const includeDetails = document.getElementById('include-details').checked;
        const notes = document.getElementById('export-notes').value;
        
        this.showLoading(true);
        
        // In a real implementation, this would call your API to generate the export
        // For this demo, we'll just show a notification after a delay
        
        setTimeout(() => {
            this.showLoading(false);
            this.hideExportModal();
            
            // Extract the report name for the notification
            let reportName = reportType.replace(/-/g, ' ');
            reportName = reportName.replace(/\b\w/g, l => l.toUpperCase());
            
            this.showNotification(`${reportName} Report exported as ${format.toUpperCase()} successfully.`);
        }, 1500);
    }
};

import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Progress } from '@/components/ui/progress';
import {
  BarChart,
  Calendar,
  DollarSign,
  Globe,
  LayoutDashboard,
  Users,
  Water,
  Heart,
  GraduationCap,
  Sprout,
  AlertCircle,
  CheckCircle2
} from 'lucide-react';

const CampaignDashboard = () => {
  // Simulated data - would come from your MongoDB in real app
  const [campaigns] = useState([
    {
      id: "65ee1a1b2f3a4b5c6d7e8f9a",
      title: "Clean Water Pipeline - Samburu County",
      location: "Samburu County, Kenya",
      category: "water-access",
      raised: 75000,
      goal: 125000,
      currency: "XLM",
      backers: 184,
      progress: 60,
      status: "active",
      beneficiaries: 2500,
      endDate: "2024-06-01"
    },
    {
      id: "65ee1a1b2f3a4b5c6d7e8f9e",
      title: "Solar-Powered Medical Clinic - Chocó",
      location: "Chocó, Colombia",
      category: "healthcare",
      raised: 30000,
      goal: 200000,
      currency: "XLM",
      backers: 45,
      progress: 15,
      status: "active",
      beneficiaries: 5000,
      endDate: "2024-11-10"
    },
    // Add other campaigns...
  ]);

  const getCategoryIcon = (category) => {
    switch (category) {
      case 'water-access':
        return <Water className="h-5 w-5 text-blue-500" />;
      case 'healthcare':
        return <Heart className="h-5 w-5 text-red-500" />;
      case 'education':
        return <GraduationCap className="h-5 w-5 text-purple-500" />;
      case 'agriculture':
        return <Sprout className="h-5 w-5 text-green-500" />;
      default:
        return <Globe className="h-5 w-5 text-gray-500" />;
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'active':
        return 'text-green-500 bg-green-50';
      case 'completed':
        return 'text-blue-500 bg-blue-50';
      case 'pending':
        return 'text-yellow-500 bg-yellow-50';
      default:
        return 'text-gray-500 bg-gray-50';
    }
  };

  return (
    <div className="p-6 max-w-7xl mx-auto">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold mb-2">Campaign Dashboard</h1>
        <p className="text-gray-600">Manage and monitor active campaigns on The Give Hub</p>
      </div>

      {/* Overview Cards */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4 mb-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Total Funds Raised</CardTitle>
            <DollarSign className="h-4 w-4 text-gray-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">105,000 XLM</div>
            <div className="text-xs text-gray-500">+12% from last month</div>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Active Campaigns</CardTitle>
            <LayoutDashboard className="h-4 w-4 text-gray-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">4</div>
            <div className="text-xs text-gray-500">Across 2 countries</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Total Beneficiaries</CardTitle>
            <Users className="h-4 w-4 text-gray-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">11,000+</div>
            <div className="text-xs text-gray-500">Direct impact</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Success Rate</CardTitle>
            <BarChart className="h-4 w-4 text-gray-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">92%</div>
            <div className="text-xs text-gray-500">Completed campaigns</div>
          </CardContent>
        </Card>
      </div>

      {/* Main Content */}
      <Tabs defaultValue="active" className="space-y-4">
        <TabsList>
          <TabsTrigger value="active">Active Campaigns</TabsTrigger>
          <TabsTrigger value="pending">Pending Approval</TabsTrigger>
          <TabsTrigger value="completed">Completed</TabsTrigger>
        </TabsList>

        <TabsContent value="active" className="space-y-4">
          <div className="grid gap-6 md:grid-cols-2">
            {campaigns.map(campaign => (
              <Card key={campaign.id} className="overflow-hidden">
                <CardHeader className="pb-4">
                  <div className="flex justify-between items-start">
                    <div>
                      <div className="flex items-center gap-2 mb-1">
                        {getCategoryIcon(campaign.category)}
                        <span className="text-sm text-gray-500 capitalize">{campaign.category}</span>
                      </div>
                      <CardTitle className="text-xl mb-1">{campaign.title}</CardTitle>
                      <div className="flex items-center text-gray-500 text-sm">
                        <Globe className="w-4 h-4 mr-1" />
                        {campaign.location}
                      </div>
                    </div>
                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(campaign.status)}`}>
                      {campaign.status.charAt(0).toUpperCase() + campaign.status.slice(1)}
                    </span>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div>
                    <div className="flex justify-between mb-2">
                      <span className="text-gray-600">Progress</span>
                      <span className="font-medium">{campaign.progress}%</span>
                    </div>
                    <Progress value={campaign.progress} className="h-2" />
                  </div>

                  <div className="grid grid-cols-3 gap-4 text-sm">
                    <div>
                      <div className="text-gray-500">Raised</div>
                      <div className="font-medium">{campaign.raised.toLocaleString()} {campaign.currency}</div>
                    </div>
                    <div>
                      <div className="text-gray-500">Backers</div>
                      <div className="font-medium">{campaign.backers}</div>
                    </div>
                    <div>
                      <div className="text-gray-500">Days Left</div>
                      <div className="font-medium">{new Date(campaign.endDate).getDate() - new Date().getDate()}</div>
                    </div>
                  </div>

                  <div className="flex gap-2 mt-4">
                    <button className="flex-1 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                      View Details
                    </button>
                    <button className="flex-1 border border-gray-300 px-4 py-2 rounded hover:bg-gray-50">
                      Edit Campaign
                    </button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>

        <TabsContent value="pending">
          <Card>
            <CardHeader>
              <CardTitle>Pending Campaigns</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-center p-8 text-gray-500">
                <AlertCircle className="mr-2" />
                No pending campaigns requiring approval
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="completed">
          <Card>
            <CardHeader>
              <CardTitle>Completed Campaigns</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-center p-8 text-green-500">
                <CheckCircle2 className="mr-2" />
                Agricultural Training Center project successfully completed
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
};

export default CampaignDashboard;
import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Progress } from '@/components/ui/progress';
import {
  BarChart,
  Calendar,
  DollarSign,
  Globe,
  LayoutDashboard,
  Users,
  Water,
  Heart,
  GraduationCap,
  Sprout,
  AlertCircle,
  CheckCircle2
} from 'lucide-react';

const CampaignDashboard = () => {
  // Simulated data - would come from your MongoDB in real app
  const [campaigns] = useState([
    {
      id: "65ee1a1b2f3a4b5c6d7e8f9a",
      title: "Clean Water Pipeline - Samburu County",
      location: "Samburu County, Kenya",
      category: "water-access",
      raised: 75000,
      goal: 125000,
      currency: "XLM",
      backers: 184,
      progress: 60,
      status: "active",
      beneficiaries: 2500,
      endDate: "2024-06-01"
    },
    {
      id: "65ee1a1b2f3a4b5c6d7e8f9e",
      title: "Solar-Powered Medical Clinic - Chocó",
      location: "Chocó, Colombia",
      category: "healthcare",
      raised: 30000,
      goal: 200000,
      currency: "XLM",
      backers: 45,
      progress: 15,
      status: "active",
      beneficiaries: 5000,
      endDate: "2024-11-10"
    },
    // Add other campaigns...
  ]);

  const getCategoryIcon = (category) => {
    switch (category) {
      case 'water-access':
        return <Water className="h-5 w-5 text-blue-500" />;
      case 'healthcare':
        return <Heart className="h-5 w-5 text-red-500" />;
      case 'education':
        return <GraduationCap className="h-5 w-5 text-purple-500" />;
      case 'agriculture':
        return <Sprout className="h-5 w-5 text-green-500" />;
      default:
        return <Globe className="h-5 w-5 text-gray-500" />;
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'active':
        return 'text-green-500 bg-green-50';
      case 'completed':
        return 'text-blue-500 bg-blue-50';
      case 'pending':
        return 'text-yellow-500 bg-yellow-50';
      default:
        return 'text-gray-500 bg-gray-50';
    }
  };

  return (
    <div className="p-6 max-w-7xl mx-auto">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold mb-2">Campaign Dashboard</h1>
        <p className="text-gray-600">Manage and monitor active campaigns on The Give Hub</p>
      </div>

      {/* Overview Cards */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4 mb-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Total Funds Raised</CardTitle>
            <DollarSign className="h-4 w-4 text-gray-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">105,000 XLM</div>
            <div className="text-xs text-gray-500">+12% from last month</div>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Active Campaigns</CardTitle>
            <LayoutDashboard className="h-4 w-4 text-gray-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">4</div>
            <div className="text-xs text-gray-500">Across 2 countries</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Total Beneficiaries</CardTitle>
            <Users className="h-4 w-4 text-gray-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">11,000+</div>
            <div className="text-xs text-gray-500">Direct impact</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Success Rate</CardTitle>
            <BarChart className="h-4 w-4 text-gray-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">92%</div>
            <div className="text-xs text-gray-500">Completed campaigns</div>
          </CardContent>
        </Card>
      </div>

      {/* Main Content */}
      <Tabs defaultValue="active" className="space-y-4">
        <TabsList>
          <TabsTrigger value="active">Active Campaigns</TabsTrigger>
          <TabsTrigger value="pending">Pending Approval</TabsTrigger>
          <TabsTrigger value="completed">Completed</TabsTrigger>
        </TabsList>

        <TabsContent value="active" className="space-y-4">
          <div className="grid gap-6 md:grid-cols-2">
            {campaigns.map(campaign => (
              <Card key={campaign.id} className="overflow-hidden">
                <CardHeader className="pb-4">
                  <div className="flex justify-between items-start">
                    <div>
                      <div className="flex items-center gap-2 mb-1">
                        {getCategoryIcon(campaign.category)}
                        <span className="text-sm text-gray-500 capitalize">{campaign.category}</span>
                      </div>
                      <CardTitle className="text-xl mb-1">{campaign.title}</CardTitle>
                      <div className="flex items-center text-gray-500 text-sm">
                        <Globe className="w-4 h-4 mr-1" />
                        {campaign.location}
                      </div>
                    </div>
                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(campaign.status)}`}>
                      {campaign.status.charAt(0).toUpperCase() + campaign.status.slice(1)}
                    </span>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div>
                    <div className="flex justify-between mb-2">
                      <span className="text-gray-600">Progress</span>
                      <span className="font-medium">{campaign.progress}%</span>
                    </div>
                    <Progress value={campaign.progress} className="h-2" />
                  </div>

                  <div className="grid grid-cols-3 gap-4 text-sm">
                    <div>
                      <div className="text-gray-500">Raised</div>
                      <div className="font-medium">{campaign.raised.toLocaleString()} {campaign.currency}</div>
                    </div>
                    <div>
                      <div className="text-gray-500">Backers</div>
                      <div className="font-medium">{campaign.backers}</div>
                    </div>
                    <div>
                      <div className="text-gray-500">Days Left</div>
                      <div className="font-medium">{new Date(campaign.endDate).getDate() - new Date().getDate()}</div>
                    </div>
                  </div>

                  <div className="flex gap-2 mt-4">
                    <button className="flex-1 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                      View Details
                    </button>
                    <button className="flex-1 border border-gray-300 px-4 py-2 rounded hover:bg-gray-50">
                      Edit Campaign
                    </button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>

        <TabsContent value="pending">
          <Card>
            <CardHeader>
              <CardTitle>Pending Campaigns</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-center p-8 text-gray-500">
                <AlertCircle className="mr-2" />
                No pending campaigns requiring approval
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="completed">
          <Card>
            <CardHeader>
              <CardTitle>Completed Campaigns</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-center p-8 text-green-500">
                <CheckCircle2 className="mr-2" />
                Agricultural Training Center project successfully completed
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
};

export default CampaignDashboard;

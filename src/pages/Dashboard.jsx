// src/pages/Dashboard.jsx
import React from 'react'
import { Link } from 'react-router-dom'
import { useAuthStore } from '../store/auth'

export default function Dashboard() {
  const user = useAuthStore((state) => state.user)
  const { updateProfile, updateStellarWallet } = useAuthStore()
  const loading = useAuthStore((state) => state.loading)

  const [isEditing, setIsEditing] = React.useState(false)
  const [profileData, setProfileData] = React.useState({
    fullName: user?.fullName || '',
    country: user?.country || '',
    stellar_public_key: user?.stellar_public_key || ''
  })

  const handleInputChange = (e) => {
    const { name, value } = e.target
    setProfileData(prev => ({
      ...prev,
      [name]: value
    }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    try {
      await updateProfile(profileData)
      setIsEditing(false)
    } catch (error) {
      console.error('Failed to update profile:', error)
    }
  }
console.log(updateStellarWallet)

  if (!user) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-600">Please log in to view your dashboard.</p>
        <Link
          to="/login"
          className="mt-4 inline-block text-blue-600 hover:text-blue-500"
        >
          Go to Login
        </Link>
      </div>
    )
  }

  return (
    <div className="max-w-4xl mx-auto space-y-8">
      {/* Header Section */}
      <div className="bg-white shadow rounded-lg p-6">
        <div className="flex justify-between items-center">
          <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
          {!isEditing && (
            <button
              onClick={() => setIsEditing(true)}
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
              Edit Profile
            </button>
          )}
        </div>
      </div>

      {/* Profile Section */}
      <div className="bg-white shadow rounded-lg p-6">
        <h2 className="text-xl font-semibold text-gray-900 mb-4">
          Profile Information
        </h2>
        
        {isEditing ? (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label 
                htmlFor="fullName" 
                className="block text-sm font-medium text-gray-700"
              >
                Full Name
              </label>
              <input
                type="text"
                id="fullName"
                name="fullName"
                value={profileData.fullName}
                onChange={handleInputChange}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              />
            </div>

            <div>
              <label 
                htmlFor="country" 
                className="block text-sm font-medium text-gray-700"
              >
                Country
              </label>
              <input
                type="text"
                id="country"
                name="country"
                value={profileData.country}
                onChange={handleInputChange}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              />
            </div>

            <div>
              <label 
                htmlFor="stellar_public_key" 
                className="block text-sm font-medium text-gray-700"
              >
                Stellar Public Key
              </label>
              <input
                type="text"
                id="stellar_public_key"
                name="stellar_public_key"
                value={profileData.stellar_public_key}
                onChange={handleInputChange}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              />
            </div>

            <div className="flex space-x-4">
              <button
                type="submit"
                disabled={loading}
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
              >
                {loading ? 'Saving...' : 'Save Changes'}
              </button>
              <button
                type="button"
                onClick={() => setIsEditing(false)}
                className="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
              >
                Cancel
              </button>
            </div>
          </form>
        ) : (
          <div className="space-y-4">
            <div>
              <h3 className="text-sm font-medium text-gray-500">Full Name</h3>
              <p className="mt-1 text-sm text-gray-900">{user.fullName}</p>
            </div>

            <div>
              <h3 className="text-sm font-medium text-gray-500">Email</h3>
              <p className="mt-1 text-sm text-gray-900">{user.email}</p>
            </div>

            <div>
              <h3 className="text-sm font-medium text-gray-500">Country</h3>
              <p className="mt-1 text-sm text-gray-900">{user.country || 'Not set'}</p>
            </div>

            <div>
              <h3 className="text-sm font-medium text-gray-500">Role</h3>
              <p className="mt-1 text-sm text-gray-900">{user.role}</p>
            </div>

            <div>
              <h3 className="text-sm font-medium text-gray-500">KYC Status</h3>
              <p className="mt-1 text-sm text-gray-900">{user.kyc_status}</p>
            </div>

            <div>
              <h3 className="text-sm font-medium text-gray-500">Stellar Wallet</h3>
              <p className="mt-1 text-sm text-gray-900 break-all">
                {user.stellar_public_key || 'Not set'}
              </p>
            </div>
          </div>
        )}
      </div>

      {/* Activity Section */}
      <div className="bg-white shadow rounded-lg p-6">
        <h2 className="text-xl font-semibold text-gray-900 mb-4">
          Recent Activity
        </h2>
        {user.role === 'campaigner' ? (
          <div className="space-y-4">
            <Link
              to="/campaigns/create"
              className="block w-full text-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
            >
              Create New Campaign
            </Link>
            {/* Add campaign listing here */}
          </div>
        ) : (
          <div className="space-y-4">
            <Link
              to="/campaigns"
              className="block w-full text-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
              Browse Campaigns
            </Link>
            {/* Add donation history here */}
          </div>
        )}
      </div>
    </div>
  )
}
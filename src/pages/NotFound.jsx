// src/pages/NotFound.jsx
import React from 'react'
import { Link } from 'react-router-dom'

export default function NotFound() {
  return (
    <div className="min-h-[60vh] flex flex-col items-center justify-center px-4 py-16">
      <div className="text-center">
        <h1 className="text-6xl font-bold text-blue-600">404</h1>
        
        <div className="mt-4">
          <h2 className="text-3xl font-semibold text-gray-900">
            Page not found
          </h2>
          <p className="mt-2 text-lg text-gray-600">
            Sorry, we couldn't find the page you're looking for.
          </p>
        </div>

        <div className="mt-8">
          <Link
            to="/campaigns"
            className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            Go back to campaigns
          </Link>
        </div>

        {/* Optional: Additional helpful links */}
        <div className="mt-6 space-y-2 text-sm text-gray-600">
          <p>You might want to:</p>
          <ul className="space-y-1">
            <li>
              <Link to="/" className="text-blue-600 hover:text-blue-500">
                Go to homepage
              </Link>
            </li>
            <li>
              <Link to="/campaigns" className="text-blue-600 hover:text-blue-500">
                Browse campaigns
              </Link>
            </li>
            <li>
              <Link to="/login" className="text-blue-600 hover:text-blue-500">
                Sign in to your account
              </Link>
            </li>
          </ul>
        </div>
      </div>
    </div>
  )
}

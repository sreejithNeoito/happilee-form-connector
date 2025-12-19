import React from "react";
import {
  HashRouter as Router,
  Routes,
  Route,
  NavLink,
  Navigate,
} from "react-router-dom";

import ApiConfig from "./components/WP-HFC-apiSettings";
import FormSettings from "./components/WP-HFC-formSettings";

const App = () => {
  return (
    <Router>
      <div className="hfc-main wphfc-p-8">
        <h1 className="wphfc-font-bold wphfc-container wphfc-font-archivo">
          Welcome to Happilee Forms Connect
        </h1>
        <p>Connect your WordPress forms to Happilee API seamlessly</p>

        <div>
          {/* Tab Navigation */}
          <nav className="wphfc-mt-6 wphfc-nav-tab">
            <ul className="wphfc-flex wphfc-mb-0">
              <li>
                <NavLink
                  to="/apiconfiguration"
                  className={({ isActive }) =>
                    `wphfc-tab-link wphfc-text-sm wphfc-font-semibold ${
                      isActive ? "wphfc-tab-active" : ""
                    }`
                  }>
                  API Configuration
                </NavLink>
              </li>
              <li>
                <NavLink
                  to="/formsettings"
                  className={({ isActive }) =>
                    `wphfc-tab-link wphfc-text-sm wphfc-font-semibold ${
                      isActive ? "wphfc-tab-active" : ""
                    }`
                  }>
                  Form Settings
                </NavLink>
              </li>
            </ul>
          </nav>

          {/* Tab Content */}
          <div className="happilee-tab-content">
            <Routes>
              <Route
                path="/"
                element={<Navigate to="/apiconfiguration" replace />}
              />
              <Route path="/apiconfiguration" element={<ApiConfig />} />
              <Route path="/formsettings" element={<FormSettings />} />
            </Routes>
          </div>
        </div>
      </div>
    </Router>
  );
};

export default App;

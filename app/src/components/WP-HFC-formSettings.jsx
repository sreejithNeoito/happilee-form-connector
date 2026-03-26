import React, { useState, useEffect } from "react";
import FomDetails from "./WP-HFC-formdetails.jsx";

const WPHFC_ApiConfiguration = () => {
  const [forms, setForms] = useState(null);
  const [activeForm, setActiveForm] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchforms();
  }, []);

  const fetchforms = async () => {
    setLoading(true);
    try {
      const response = await fetch(`${happileeConnect.rest_url}fetch-forms`, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": happileeConnect.happfoco_nonce,
        },
        credentials: "same-origin",
      });

      if (response.ok) {
        const data = await response.json();
        setForms(data);

        // Auto-select first plugin if available
        if (data?.plugins && data.plugins.length > 0) {
          setActiveForm(data.plugins[0]);
        }
      }
    } catch (error) {
      console.error("Error fetching forms:", error);
    } finally {
      setLoading(false);
    }
  };

  // Check if forms data exists and has plugins
  const hasPlugins =
    forms?.plugins && Array.isArray(forms.plugins) && forms.plugins.length > 0;

  return (
    <div className="wphfc-formsetting">
      <div className="wphfc-bg-white wphfc-rounded-lg wphfc-shadow-md wphfc-p-8">
        <div className="wphfc-header">
          <h2 className="wphfc-text-2xl wphfc-font-bold wphfc-text-primary">
            Available Forms to Connect with Us
          </h2>
          <p className="wphfc-text-[14px] wphfc-text-gray-600 wphfc-mt-2">
            Here is a list of all WordPress forms that can be connected through
            our plugin. Each form uses native form hooks to capture submissions
            and forward the data to happilee platform.
          </p>
        </div>
      </div>

      <div className="wphfc-w-full md:wphfc-flex wphfc-gap-5 wphfc-pt-5">
        <div className="md:wphfc-w-[20%] wphfc-w-full whfc-form-selector wphfc-bg-white wphfc-rounded-lg wphfc-shadow-md wphfc-overflow-hidden md:wphfc-mb-0 wphfc-mb-5">
          {loading ? (
            <div className="wphfc-p-4 wphfc-text-center wphfc-text-gray-500">
              <p>Loading forms...</p>
            </div>
          ) : !hasPlugins ? (
            <div className="wphfc-p-4 wphfc-text-center wphfc-text-gray-500">
              <p className="wphfc-text-sm">No form plugins found</p>
              <p className="wphfc-text-xs wphfc-mt-2">
                Please install a supported form plugin
              </p>
            </div>
          ) : (
            <ul className="wphfc-text-[16px]">
              {forms.plugins.map((plugin) => (
                <li
                  key={plugin.type}
                  className="wphfc-border-b wphfc-border-gray-200 last:wphfc-border-b-0">
                  <button
                    onClick={() => setActiveForm(plugin)}
                    className={`wphfc-block wphfc-w-full wphfc-text-left wphfc-p-4 wphfc-border-0 wphfc-outline-none wphfc-transition-all wphfc-duration-200
                      ${
                        activeForm?.type === plugin.type
                          ? "wphfc-bg-primary wphfc-text-white wphfc-font-semibold"
                          : "wphfc-bg-white wphfc-text-gray-700 hover:wphfc-bg-gray-50"
                      }
                    `}>
                    <div className="wphfc-flex wphfc-items-center wphfc-justify-between">
                      <span>{plugin.displayName}</span>
                    </div>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="md:wphfc-w-[80%] wphfc-w-full wphfc-bg-white wphfc-rounded-lg wphfc-shadow-md">
          <FomDetails activeForm={activeForm} />
        </div>
      </div>
    </div>
  );
};

export default WPHFC_ApiConfiguration;

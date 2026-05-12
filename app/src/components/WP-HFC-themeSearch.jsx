import React from "react";

const WPHFC_TemplateSearch = ({ searchQuery, onSearch }) => {
  return (
    <div className="wphfc-relative wphfc-items-center">
      <input
        type="text"
        placeholder="Search templates..."
        value={searchQuery}
        onChange={(e) => onSearch(e.target.value)}
        style={{ height: "32px", fontSize: "14px" }}
        className="wphfc-pl-7 wphfc-pr-3 wphfc-py-0 wphfc-border wphfc-border-gray-300 wphfc-rounded-md wphfc-outline-none focus:wphfc-ring-2 focus:wphfc-ring-blue-500 wphfc-w-48 wphfc-text-gray-700 wphfc-bg-white placeholder:wphfc-text-gray-400"
      />
    </div>
  );
};

export default WPHFC_TemplateSearch;

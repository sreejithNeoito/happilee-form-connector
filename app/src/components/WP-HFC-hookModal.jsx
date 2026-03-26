import React, { useState, useRef, useEffect } from "react";
import { createPortal } from "react-dom";
import { ToastContainer, toast } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";

const WPHFC_HookModal = ({
  selectedFormId,
  formType,
  onClose,
  activeHook,
  formName,
  CurrentFields,
}) => {
  const [selectedHook, setSelectedHook] = useState("");
  const [error, setError] = useState("");
  const [isSaving, setIsSaving] = useState(false);
  const [formFields, setFormFields] = useState([]);
  const [fieldMappings, setFieldMappings] = useState({});
  const [openDropdown, setOpenDropdown] = useState(null);
  const [isHookDropdownOpen, setIsHookDropdownOpen] = useState(false);
  const [isClosing, setIsClosing] = useState(false);
  const [autoCountryCode, setAutoCountryCode] = useState("");
  const [isFetchingCountryCode, setIsFetchingCountryCode] = useState(false);

  const hookDropdownRef = useRef(null);
  const fieldDropdownRefs = useRef({});

  // ─── Label ↔ Storage-key maps ────────────────────────────────────────────
  // UI always shows the human-readable label ("First Name").
  // Data is saved / loaded using the snake_case key ("first_name").
  const fieldLabelToKey = {
    "First Name": "first_name",
    "Last Name": "last_name",
    Mobile: "phone_number",
    "Country Code": "country_code",
    Birthday: "birthday",
    Tags: "tags",
  };

  // Reverse map – used when rehydrating saved DB data back into UI state
  const fieldKeyToLabel = Object.fromEntries(
    Object.entries(fieldLabelToKey).map(([label, key]) => [key, label]),
  );
  // ─────────────────────────────────────────────────────────────────────────

  // Toast Notification
  const notify = (message, type = "success") => {
    toast[type](message, {
      position: "top-right",
      autoClose: 3000,
      hideProgressBar: false,
      closeOnClick: true,
      pauseOnHover: true,
      draggable: true,
    });
  };

  // Close dropdowns when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        hookDropdownRef.current &&
        !hookDropdownRef.current.contains(event.target)
      ) {
        setIsHookDropdownOpen(false);
      }
      if (openDropdown) {
        const currentRef = fieldDropdownRefs.current[openDropdown];
        if (currentRef && !currentRef.contains(event.target)) {
          setOpenDropdown(null);
        }
      }
    };

    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [isHookDropdownOpen, openDropdown]);

  useEffect(() => {
    getFormFields(selectedFormId, formType);
  }, [selectedFormId, formType]);

  // Initialize field mappings from database when modal opens.
  // DB stores keys as snake_case ("first_name") → convert back to display
  // labels ("First Name") so the UI state is always label-keyed.
  useEffect(() => {
    const formKey = String(selectedFormId);
    const savedFields = CurrentFields[formKey];
    if (savedFields) {
      try {
        const parsedFields =
          typeof savedFields === "string"
            ? JSON.parse(savedFields)
            : savedFields;

        if (parsedFields && typeof parsedFields === "object") {
          const labelKeyedMappings = {};
          Object.entries(parsedFields).forEach(
            ([storedKey, formFieldValue]) => {
              // Convert snake_case key → display label, fall back to raw key
              const displayLabel = fieldKeyToLabel[storedKey] || storedKey;
              labelKeyedMappings[displayLabel] = formFieldValue;
            },
          );
          setFieldMappings(labelKeyedMappings);
        }
      } catch (err) {
        console.error("Error parsing saved fields:", err);
      }
    }
  }, [CurrentFields, selectedFormId]);

  const getAvailableHooks = (formType) => {
    const hooks = {
      cf7: [
        { value: "wpcf7_mail_sent", label: "Mail Sent ( Recommended )" },
        { value: "wpcf7_before_send_mail", label: "Before Send Mail" },
        { value: "wpcf7_mail_failed", label: "Mail Failed" },
        { value: "wpcf7_submit", label: "On Submit" },
      ],
      wpforms: [
        {
          value: "wpforms_process_complete",
          label: "Process Complete ( Recommended )",
        },
        { value: "wpforms_process_before", label: "Before Process" },
        { value: "wpforms_process_after", label: "After Process" },
      ],
      ninja_forms: [
        {
          value: "ninja_forms_after_submission",
          label: "After Submission",
        },
      ],
      forminator: [
        {
          value: "forminator_form_after_save_entry",
          label: "After Save Entry ( Recommended )",
        },
        {
          value: "forminator_custom_form_submit_before_set_fields",
          label: "Before Set Fields",
        },
      ],
    };
    return hooks[formType] || [];
  };

  // Human-readable labels shown in the UI (unchanged)
  const getAvilableFields = [
    "First Name",
    "Last Name",
    "Mobile",
    "Country Code",
    "Birthday",
    "Tags",
  ];

  const getFormFields = async (formId, formType) => {
    try {
      const response = await fetch(
        `${happileeConnect.rest_url}fetch-form-fields`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": happileeConnect.happfoco_nonce,
          },
          body: JSON.stringify({
            form_id: formId,
            form_type: formType,
          }),
          credentials: "same-origin",
        },
      );
      const data = await response.json();
      if (response.ok && data.success) {
        setFormFields(data.fields);
      }
    } catch (error) {
      console.log(error);
    }
  };

  const toggleDropdown = (fieldName) => {
    setOpenDropdown(openDropdown === fieldName ? null : fieldName);
  };

  // Initialize selected hook when modal opens
  useEffect(() => {
    const availableHooks = getAvailableHooks(formType);
    if (activeHook && availableHooks.some((h) => h.value === activeHook)) {
      setSelectedHook(activeHook);
    } else if (availableHooks.length > 0) {
      setSelectedHook(availableHooks[0].value);
    }
  }, [activeHook, formType]);

  const handleClose = (shouldRefresh) => {
    setIsClosing(true);
    setTimeout(() => {
      onClose(shouldRefresh);
    }, 300);
  };

  const handleHookSelect = (hookValue) => {
    setSelectedHook(hookValue);
    setIsHookDropdownOpen(false);
    setError("");
  };

  const getSelectedHookLabel = () => {
    const availableHooks = getAvailableHooks(formType);
    const hook = availableHooks.find((h) => h.value === selectedHook);
    return hook ? hook.label : "Please Select the Hook";
  };

  // fieldMappings state is always keyed by display label ("First Name" → "form-field-name")
  const handleFieldMapping = (happileeField, formField) => {
    let fieldValue = null;
    if (formField) {
      switch (formType) {
        case "wpforms":
          fieldValue = formField.name;
          break;
        case "cf7":
          fieldValue = formField.name;
          break;
        case "ninja_forms":
          fieldValue = formField.name;
          break;
        case "forminator":
          fieldValue = formField.id || formField.name;
          break;
        default:
          fieldValue = formField.name || formField.id;
      }
    }

    setFieldMappings((prev) => ({
      ...prev,
      [happileeField]: fieldValue,
    }));
    setOpenDropdown(null);
  };

  // Helper: display label for the currently mapped form field
  const getMappedFieldLabel = (happileeField) => {
    const mappedValue = fieldMappings[happileeField];

    if (
      happileeField === "Country Code" &&
      (!mappedValue || mappedValue === "country-code")
    ) {
      if (isFetchingCountryCode) return "Detecting...";
      if (autoCountryCode) return `Auto: ${autoCountryCode}`;
      return "Country Code";
    }

    if (!mappedValue) return happileeField;

    const formField = formFields.find((f) => {
      switch (formType) {
        case "wpforms":
          return (f.id || f.name) === mappedValue;
        case "cf7":
          return f.name === mappedValue;
        case "ninja_forms":
          return f.name === mappedValue;
        case "forminator":
          return (f.id || f.name) === mappedValue;
        default:
          return (f.name || f.id) === mappedValue;
      }
    });

    return formField ? formField.label || formField.name : mappedValue;
  };

  // Helper: highlight the currently selected option in the dropdown
  const isFieldSelected = (happileeField, formField) => {
    const mappedValue = fieldMappings[happileeField];
    if (!mappedValue || mappedValue === "country-code") return false;

    switch (formType) {
      case "wpforms":
        return mappedValue === (formField.id || formField.name);
      case "cf7":
        return mappedValue === formField.name;
      case "ninja_forms":
        return mappedValue === formField.name;
      case "forminator":
        return mappedValue === (formField.id || formField.name);
      default:
        return mappedValue === (formField.name || formField.id);
    }
  };

  // Fetch country code from IP if Country Code field is not mapped

  useEffect(() => {
    fetchCountryCodeFromIP();
  }, []);

  const fetchCountryCodeFromIP = async () => {
    setIsFetchingCountryCode(true);
    try {
      const response = await fetch("https://ipapi.co/json/");
      const data = await response.json();
      if (data && data.country_calling_code) {
        setAutoCountryCode(data.country_calling_code); // e.g. "+91"
      }
    } catch (error) {
      console.error("Failed to fetch country code from IP:", error);
      setAutoCountryCode("+1");
    } finally {
      setIsFetchingCountryCode(false);
    }
  };

  const saveFormSettings = async () => {
    if (!selectedHook) {
      setError("Please select a hook");
      return;
    }

    if (!fieldMappings["Mobile"]) {
      notify(
        "Phone field is required. Settings cannot be saved without mapping the Mobile field.",
        "error",
      );
      return;
    }

    setIsSaving(true);
    setError("");

    // Convert display-label keys → snake_case keys before sending to the API.
    // e.g. { "First Name": "your-cf7-field" } → { "first_name": "your-cf7-field" }
    const keyedFieldMappings = {};
    Object.entries(fieldMappings).forEach(([displayLabel, formFieldValue]) => {
      if (formFieldValue !== null && formFieldValue !== undefined) {
        const storageKey = fieldLabelToKey[displayLabel] || displayLabel;
        keyedFieldMappings[storageKey] = formFieldValue;
      }
    });

    // Set country code if the mapping country code section is empty
    if (!keyedFieldMappings["country_code"]) {
      keyedFieldMappings["country_code"] = "country-code";
    }

    try {
      const response = await fetch(
        `${happileeConnect.rest_url}save-form-settings`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": happileeConnect.happfoco_nonce,
          },
          body: JSON.stringify({
            form_id: String(selectedFormId),
            form_name: formName || "",
            form_type: formType,
            active_hook: selectedHook,
            is_enabled: 1,
            form_field: keyedFieldMappings, // ← snake_case keys
          }),
          credentials: "same-origin",
        },
      );

      const data = await response.json();

      if (response.ok && data.success) {
        notify("Settings saved successfully!", "success");
        setTimeout(() => {
          handleClose(true);
        }, 500);
      } else {
        notify(data.message || "Failed to save hook", "error");
      }
    } catch (error) {
      notify("An error occurred while saving the hook", "error");
    } finally {
      setIsSaving(false);
    }
  };

  const availableHooks = getAvailableHooks(formType);

  return (
    <>
      {/* Toast Container rendered via Portal to document.body */}
      {createPortal(
        <ToastContainer
          position="top-right"
          autoClose={3000}
          hideProgressBar={false}
          newestOnTop={true}
          closeOnClick
          rtl={false}
          pauseOnFocusLoss
          draggable
          pauseOnHover
          theme="light"
          style={{ zIndex: 9999999 }}
        />,
        document.body,
      )}
      <div
        className={`wphfc-modal-backdrop wphfc-fixed wphfc-inset-0 wphfc-flex wphfc-items-center wphfc-justify-center wphfc-bg-black/50 wphfc-z-50 ${isClosing ? "wphfc-opacity-0" : "wphfc-opacity-100"}`}
        style={{ zIndex: 100000 }}
        onClick={(e) => {
          if (e.target === e.currentTarget && !isSaving) {
            handleClose(false);
          }
        }}>
        <div
          className={`wphfc_hook_popup wphfc-max-w-md wphfc-w-full wphfc-mx-4 wphfc-bg-white wphfc-p-6 wphfc-rounded-lg wphfc-shadow-lg wphfc-transition-transform wphfc-duration-300 ${
            isClosing
              ? "wphfc-scale-95 wphfc-opacity-0"
              : "wphfc-scale-100 wphfc-opacity-100"
          }`}>
          <div className="wphfc-flex wphfc-justify-between wphfc-items-center wphfc-mb-4">
            <h3 className="wphfc-text-lg wphfc-font-semibold wphfc-m-0">
              Select Hook for Form ID: {selectedFormId}
            </h3>
            <button
              className="wphfc-modal-close wphfc-p-1 wphfc-bg-transparent wphfc-border-none wphfc-cursor-pointer"
              onClick={() => handleClose(false)}
              disabled={isSaving}>
              <svg
                className="wphfc-w-5 wphfc-h-5"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M17 7L7 17M7 7L17 17"
                  stroke="currentColor"
                  strokeWidth="1.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </svg>
            </button>
          </div>

          <div className="wphfc-mb-4 wphfc-relative" ref={hookDropdownRef}>
            <label className="wphfc-block wphfc-text-sm wphfc-font-medium wphfc-mb-2 wphfc-text-gray-700">
              Form Name: <strong>{formName}</strong>
            </label>

            {/* Hook Selection Dropdown */}
            <button
              type="button"
              className="wphfc-w-full wphfc-items-center wphfc-bg-white wphfc-flex wphfc-justify-between wphfc-modal-select wphfc-p-2 wphfc-select wphfc-rounded-md"
              onClick={() => setIsHookDropdownOpen(!isHookDropdownOpen)}
              disabled={isSaving}>
              <span
                className={
                  selectedHook ? "wphfc-text-gray-900" : "wphfc-text-gray-500"
                }>
                {getSelectedHookLabel()}
              </span>
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                className={`wphfc-transition-transform`}>
                <path
                  d="M6 9L12 15L18 9"
                  stroke="currentColor"
                  strokeWidth="1.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </svg>
            </button>
            {isHookDropdownOpen && (
              <ul className="wphfc-w-full wphfc-max-h-60 wphfc-bg-white wphfc-rounded-md wphfc-absolute wphfc-z-10 wphfc-shadow-xl wphfc-py-1 wphfc-mt-1 wphfc-border wphfc-border-gray-300 wphfc-overflow-y-auto">
                {availableHooks.map((hook) => (
                  <li key={hook.value}>
                    <button
                      type="button"
                      className={`wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-text-sm hover:wphfc-bg-blue-50 wphfc-cursor-pointer wphfc-border-none wphfc-bg-transparent ${
                        selectedHook === hook.value
                          ? "wphfc-bg-blue-100 wphfc-text-[#0B3966] wphfc-font-medium"
                          : "wphfc-text-gray-700"
                      }`}
                      onClick={() => handleHookSelect(hook.value)}>
                      {hook.label}
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>

          {error && (
            <div className="wphfc-mb-4 wphfc-p-3 wphfc-bg-red-50 wphfc-border wphfc-border-red-200 wphfc-rounded-md">
              <p className="wphfc-text-sm wphfc-text-red-600 wphfc-m-0">
                {error}
              </p>
            </div>
          )}

          <div className="wphfc-mb-4 wphfc-p-3 wphfc-bg-blue-50 wphfc-border wphfc-border-blue-200 wphfc-rounded-md">
            <p className="wphfc-text-xs wphfc-text-gray-700 wphfc-m-0">
              <strong>Note:</strong> Select the appropriate hook based on when
              you want to trigger the action in the form submission process.
            </p>
          </div>

          <div className="wphfc-field-mapping wphfc-mb-6 wphfc-pt-4 wphfc-border-t wphfc-border-gray-300">
            <h3 className="wphfc-text-base wphfc-font-semibold wphfc-mb-4 wphfc-text-gray-800">
              Happilee Field Mapping
            </h3>
            <div className="wphfc-space-y-3">
              {getAvilableFields.map((field) => (
                <div
                  key={field}
                  className="wphfc-field-map-row wphfc-flex wphfc-items-center wphfc-gap-3">
                  {/* Display label is always human-readable */}
                  <label className="wphfc-text-sm wphfc-font-medium wphfc-text-gray-700 wphfc-w-32 wphfc-flex-shrink-0">
                    {field}
                  </label>
                  <div
                    className="wphfc-field-dropdown-container wphfc-flex-1 wphfc-relative"
                    ref={(el) => (fieldDropdownRefs.current[field] = el)}>
                    <button
                      type="button"
                      className="wphfc-select wphfc-flex wphfc-justify-between wphfc-items-center wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-bg-white wphfc-border wphfc-border-gray-300 wphfc-rounded-md wphfc-text-left wphfc-text-sm wphfc-text-gray-700 hover:wphfc-border-gray-400 focus:wphfc-outline-none focus:wphfc-ring-2 focus:wphfc-ring-blue-500 wphfc-cursor-pointer"
                      onClick={() => toggleDropdown(field)}>
                      <span
                        className={
                          fieldMappings[field]
                            ? "wphfc-text-gray-900"
                            : "wphfc-text-gray-500"
                        }>
                        {getMappedFieldLabel(field)}
                      </span>
                      <svg
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        className={`wphfc-transition-transform ${openDropdown === field ? "wphfc-rotate-180" : ""}`}>
                        <path
                          d="M6 9L12 15L18 9"
                          stroke="currentColor"
                          strokeWidth="1.5"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                        />
                      </svg>
                    </button>
                    {openDropdown === field && (
                      <div className="wphfc-dropdown-menu wphfc-absolute wphfc-z-10 wphfc-w-full wphfc-mt-1 wphfc-bg-white wphfc-border wphfc-border-gray-300 wphfc-rounded-md wphfc-shadow-xl wphfc-max-h-60 wphfc-overflow-y-auto">
                        <ul className="wphfc-py-1">
                          <li>
                            <button
                              type="button"
                              className="wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-text-sm wphfc-text-gray-500 hover:wphfc-bg-gray-100 wphfc-cursor-pointer wphfc-border-none wphfc-bg-transparent"
                              onClick={() => handleFieldMapping(field, null)}>
                              -- None --
                            </button>
                          </li>
                          {formFields.length > 0 ? (
                            formFields.map((formField) => (
                              <li key={formField.id || formField.name}>
                                <button
                                  type="button"
                                  className={`wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-text-sm hover:wphfc-bg-blue-50 wphfc-cursor-pointer wphfc-border-none wphfc-bg-transparent ${
                                    isFieldSelected(field, formField)
                                      ? "wphfc-bg-blue-100 wphfc-text-[#0B3966] wphfc-font-medium"
                                      : "wphfc-text-gray-700"
                                  }`}
                                  onClick={() =>
                                    handleFieldMapping(field, formField)
                                  }>
                                  {formField.label || formField.name}
                                </button>
                              </li>
                            ))
                          ) : (
                            <li className="wphfc-px-3 wphfc-py-2 wphfc-text-sm wphfc-text-gray-500 wphfc-italic">
                              No fields available
                            </li>
                          )}
                        </ul>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="wphfc-flex wphfc-justify-end wphfc-gap-3">
            <button
              className="wphfc-cancel-modal wphfc-px-4 wphfc-py-2 wphfc-text-sm wphfc-font-medium wphfc-bg-red-600 wphfc-text-white wphfc-border-none wphfc-rounded-md wphfc-cursor-pointer hover:wphfc-bg-red-700 disabled:wphfc-opacity-50 disabled:wphfc-cursor-not-allowed wphfc-transition-colors"
              onClick={() => handleClose(false)}
              disabled={isSaving}>
              Cancel
            </button>
            <button
              className="wphfc-save-modal wphfc-px-4 wphfc-py-2 wphfc-text-sm wphfc-font-medium wphfc-bg-green-600 wphfc-text-white wphfc-border-none wphfc-rounded-md wphfc-cursor-pointer hover:wphfc-bg-green-700 disabled:wphfc-opacity-50 disabled:wphfc-cursor-not-allowed wphfc-transition-colors"
              onClick={saveFormSettings}
              disabled={isSaving || !selectedHook}>
              {isSaving ? "Saving..." : "Save Settings"}
            </button>
          </div>
        </div>
      </div>
    </>
  );
};

export default WPHFC_HookModal;

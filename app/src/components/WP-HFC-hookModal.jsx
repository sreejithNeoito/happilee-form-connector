import React, { useState, useEffect } from "react";

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

  useEffect(() => {
    getFormFields(selectedFormId, formType);
  }, [selectedFormId, formType]);

  // Initialize field mappings from database when modal opens
  useEffect(() => {
    const formKey = String(selectedFormId);
    const savedFields = CurrentFields[formKey];
    if (savedFields) {
      try {
        // Parse the JSON string from database
        const parsedFields =
          typeof savedFields === "string"
            ? JSON.parse(savedFields)
            : savedFields;

        // Set the field mappings
        if (parsedFields && typeof parsedFields === "object") {
          setFieldMappings(parsedFields);
        }
      } catch (error) {
        console.error("Error parsing saved fields:", error);
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
        { value: "wpforms_entry_save", label: "Entry Save" },
      ],
      ninja_forms: [
        {
          value: "ninja_forms_after_submission",
          label: "After Submission ( Recommended )",
        },
        {
          value: "ninja_forms_submit_data",
          label: "Submit Data ( Before Processing )",
        },
      ],
      forminator: [
        {
          value: "forminator_form_after_save_entry",
          label: "After Save Entry ( Recommended )",
        },
        {
          value: "forminator_form_ajax_submit_response",
          label: "AJAX Submit Response",
        },
        {
          value: "forminator_form_submit_response",
          label: "Form Submit Response ( For non-AJAX forms )",
        },
      ],
    };
    return hooks[formType] || [];
  };

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
            "X-WP-Nonce": happileeConnect.wphfc_nonce,
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

  const handleSelectChange = (e) => {
    const newValue = e.target.value;
    setSelectedHook(newValue);
    setError("");
  };

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

  // Helper function to get the display label for a mapped field
  const getMappedFieldLabel = (happileeField) => {
    const mappedValue = fieldMappings[happileeField];
    if (!mappedValue) return happileeField;

    // Find the form field that matches this mapping
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

  // Helper function to check if a field is selected
  const isFieldSelected = (happileeField, formField) => {
    const mappedValue = fieldMappings[happileeField];
    if (!mappedValue) return false;

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

  const saveFormSettings = async () => {
    if (!selectedHook) {
      setError("Please select a hook");
      return;
    }

    setIsSaving(true);
    setError("");

    try {
      const response = await fetch(
        `${happileeConnect.rest_url}save-form-settings`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": happileeConnect.wphfc_nonce,
          },
          body: JSON.stringify({
            form_id: String(selectedFormId),
            form_name: formName || "",
            form_type: formType,
            active_hook: selectedHook,
            is_enabled: 1,
            form_field: fieldMappings,
          }),
          credentials: "same-origin",
        },
      );

      const data = await response.json();

      if (response.ok && data.success) {
        onClose(true);
      } else {
        setError(data.message || "Failed to save hook");
      }
    } catch (error) {
      setError("An error occurred while saving the hook");
    } finally {
      setIsSaving(false);
    }
  };

  const availableHooks = getAvailableHooks(formType);

  return (
    <div className="wphfc-fixed wphfc-inset-0 wphfc-flex wphfc-items-center wphfc-justify-center wphfc-bg-black/50 wphfc-z-50">
      <div className="wphfc_hook_popup wphfc-max-w-md wphfc-w-full wphfc-mx-4 wphfc-bg-white wphfc-p-6 wphfc-rounded-lg wphfc-shadow-lg">
        <div className="wphfc-flex wphfc-justify-between wphfc-items-center wphfc-mb-4">
          <h3 className="wphfc-text-lg wphfc-font-semibold wphfc-m-0">
            Select Hook for Form ID: {selectedFormId}
          </h3>
          <button
            className="wphfc-modal-close wphfc-p-1 wphfc-bg-transparent wphfc-border-none wphfc-cursor-pointer"
            onClick={() => onClose(false)}
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

        <div className="wphfc-mb-4">
          <label className="wphfc-block wphfc-text-sm wphfc-font-medium wphfc-mb-2 wphfc-text-gray-700">
            Form Name: <strong>{formName}</strong>
          </label>

          <select
            className="wphfc-w-full wphfc-modal-select wphfc-p-2 wphfc-border wphfc-border-gray-300 wphfc-rounded-md focus:wphfc-outline-none focus:wphfc-ring-2 focus:wphfc-ring-blue-500"
            value={selectedHook}
            onChange={handleSelectChange}
            disabled={isSaving}>
            {availableHooks.map((hook) => (
              <option key={hook.value} value={hook.value}>
                {hook.label}
              </option>
            ))}
          </select>
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
            <strong>Note:</strong> Select the appropriate hook based on when you
            want to trigger the action in the form submission process.
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
                <label className="wphfc-text-sm wphfc-font-medium wphfc-text-gray-700 wphfc-w-32 wphfc-flex-shrink-0">
                  {field}
                </label>
                <div className="wphfc-field-dropdown-container wphfc-flex-1 wphfc-relative">
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
                    <div className="wphfc-dropdown-menu wphfc-absolute wphfc-z-10 wphfc-w-full wphfc-mt-1 wphfc-bg-white wphfc-border wphfc-border-gray-300 wphfc-rounded-md wphfc-shadow-lg wphfc-max-h-60 wphfc-overflow-y-auto">
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
            onClick={() => onClose(false)}
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
  );
};

export default WPHFC_HookModal;

import React, { useState, useEffect } from "react";

const WPHFC_HookModal = ({
  selectedFormId,
  formType,
  onClose,
  activeHook,
  formName,
}) => {
  const [selectedHook, setSelectedHook] = useState("");
  const [error, setError] = useState("");
  const [isSaving, setIsSaving] = useState(false);

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

  // Initialize selected hook when modal opens
  useEffect(() => {
    const availableHooks = getAvailableHooks(formType);
    if (activeHook && availableHooks.some((h) => h.value === activeHook)) {
      // If activeHook exists and is valid, use it
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

  const saveActiveHook = async () => {
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
          }),
          credentials: "same-origin",
        }
      );

      const data = await response.json();

      if (response.ok && data.success) {
        onClose(true); // Pass true to refresh parent data
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

        <div className="wphfc-flex wphfc-justify-end wphfc-gap-3">
          <button
            className="wphfc-cancel-modal wphfc-px-4 wphfc-py-2 wphfc-text-sm wphfc-font-medium wphfc-bg-[#e20f0f] wphfc-text-white wphfc-border-none wphfc-rounded-md wphfc-cursor-pointer hover:wphfc-opacity-90 disabled:wphfc-opacity-50 disabled:wphfc-cursor-not-allowed"
            onClick={() => onClose(false)}
            disabled={isSaving}>
            Cancel
          </button>
          <button
            className="wphfc-save-modal wphfc-px-4 wphfc-py-2 wphfc-text-sm wphfc-font-medium wphfc-bg-[#11c204fa] wphfc-text-white wphfc-border-none wphfc-rounded-md wphfc-cursor-pointer hover:wphfc-opacity-90 disabled:wphfc-opacity-50 disabled:wphfc-cursor-not-allowed"
            onClick={saveActiveHook}
            disabled={isSaving || !selectedHook}>
            {isSaving ? "Saving..." : "Save Hook"}
          </button>
        </div>
      </div>
    </div>
  );
};

export default WPHFC_HookModal;

import React from "react";

const CHAT_BACKGROUND = `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d4cfc7' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`;

/*
 * Splits the template body on {{param}} placeholders and renders each one as a
 * chip: green with the mapped form field label, or amber while still unmapped.
 */
const renderBody = (body, getParamLabel) =>
  body.split(/(\{\{[^}]+\}\})/g).map((part, idx) => {
    const match = part.match(/^\{\{([^}]+)\}\}$/);
    if (!match) return <React.Fragment key={idx}>{part}</React.Fragment>;

    const label = getParamLabel(match[1]);
    return (
      <span
        key={idx}
        className="wphfc-inline-block wphfc-px-1 wphfc-rounded wphfc-font-medium"
        style={
          label
            ? { background: "#bbf7d0", color: "#166534" }
            : { background: "#fef3c7", color: "#92400e" }
        }>
        {label ? `[${label}]` : part}
      </span>
    );
  });

const WPHFC_WhatsappPreview = ({ template, getParamLabel }) => {
  const now = new Date().toLocaleTimeString([], {
    hour: "2-digit",
    minute: "2-digit",
  });
  const header = typeof template?.header === "string" ? template.header : "";

  return (
    <div className="wphfc-border wphfc-border-solid wphfc-border-gray-200 wphfc-rounded-xl wphfc-overflow-hidden wphfc-shadow-[0_2px_8px_rgba(0,0,0,0.08)] wphfc-flex wphfc-flex-col">
      {/* WhatsApp Top Bar */}
      <div
        className="wphfc-flex wphfc-items-center wphfc-gap-2 wphfc-px-3 wphfc-py-2"
        style={{ background: "#075e54" }}>
        <div className="wphfc-w-[34px] wphfc-h-[34px] wphfc-bg-[#25d366] wphfc-shrink-0 wphfc-flex wphfc-items-center wphfc-justify-center wphfc-rounded-full">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="white">
            <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
          </svg>
        </div>
        <div>
          <p className="wphfc-text-white wphfc-text-sm wphfc-font-semibold wphfc-m-0">
            User Name
          </p>
          <p className="wphfc-m-0" style={{ color: "#b2dfdb", fontSize: "11px" }}>
            online
          </p>
        </div>
      </div>

      {/* Chat Area */}
      <div
        className="wphfc-flex wphfc-flex-col wphfc-px-3 wphfc-py-4 wphfc-overflow-y-auto"
        style={{
          background: "#efeae2",
          backgroundImage: CHAT_BACKGROUND,
          minHeight: "320px",
        }}>
        <div className="wphfc-flex wphfc-justify-center wphfc-mb-3">
          <span
            className="wphfc-px-3 wphfc-py-1 wphfc-rounded-full"
            style={{
              background: "rgba(255,255,255,0.75)",
              color: "#555",
              fontSize: "11px",
            }}>
            TODAY
          </span>
        </div>

        {template ? (
          <div className="wphfc-flex wphfc-flex-col wphfc-items-end">
            <div
              style={{
                background: "#dcf8c6",
                borderRadius: "8px 0px 8px 8px",
                padding: "8px 10px 22px 10px",
                maxWidth: "88%",
                minWidth: "120px",
                boxShadow: "0 1px 2px rgba(0,0,0,0.15)",
                position: "relative",
              }}>
              {/* Bubble Tail */}
              <div
                style={{
                  position: "absolute",
                  top: 0,
                  right: "-8px",
                  width: 0,
                  height: 0,
                  borderLeft: "8px solid #dcf8c6",
                  borderBottom: "8px solid transparent",
                }}
              />
              {header && (
                <p
                  className="wphfc-m-0 wphfc-mb-1 wphfc-font-semibold"
                  style={{ fontSize: "12px", color: "#111" }}>
                  {header}
                </p>
              )}
              <p
                className="wphfc-m-0"
                style={{
                  fontSize: "12px",
                  color: "#111",
                  whiteSpace: "pre-wrap",
                  lineHeight: "1.6",
                  wordBreak: "break-word",
                }}>
                {template.body
                  ? renderBody(template.body, getParamLabel)
                  : "Loading template..."}
              </p>
              {template.footer && (
                <p
                  className="wphfc-m-0 wphfc-mt-1"
                  style={{ fontSize: "11px", color: "#888" }}>
                  {template.footer}
                </p>
              )}
              <div
                className="wphfc-flex wphfc-items-center wphfc-gap-1"
                style={{ position: "absolute", bottom: "5px", right: "8px" }}>
                <span style={{ fontSize: "10px", color: "#888" }}>{now}</span>
                <svg width="16" height="10" viewBox="0 0 16 10" fill="none">
                  <path
                    d="M1 5l3.5 3.5L11 1"
                    stroke="#53bdeb"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                  <path
                    d="M5 5l3.5 3.5L15 1"
                    stroke="#53bdeb"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              </div>
            </div>

            {template.buttonArray?.length > 0 && (
              <div
                className="wphfc-flex wphfc-flex-col wphfc-gap-1 wphfc-mt-1"
                style={{ maxWidth: "88%", minWidth: "120px" }}>
                {template.buttonArray.map((btn, idx) => (
                  <div
                    key={idx}
                    className="wphfc-text-center wphfc-text-xs wphfc-font-medium wphfc-py-1 wphfc-px-3 wphfc-rounded-lg"
                    style={{
                      background: "white",
                      color: "#0a85d1",
                      boxShadow: "0 1px 2px rgba(0,0,0,0.15)",
                    }}>
                    {btn.text}
                  </div>
                ))}
              </div>
            )}
          </div>
        ) : (
          <div className="wphfc-flex-1 wphfc-flex wphfc-items-center wphfc-justify-center">
            <p
              className="wphfc-m-0 wphfc-text-center wphfc-px-4 wphfc-py-3 wphfc-rounded-lg"
              style={{
                background: "rgba(255,255,255,0.8)",
                color: "#6b7280",
                fontSize: "12px",
              }}>
              Select a template to see how the message will look.
            </p>
          </div>
        )}
      </div>

      {/* Input Bar */}
      <div
        className="wphfc-flex wphfc-items-center wphfc-gap-2 wphfc-px-2 wphfc-py-2"
        style={{ background: "#f0f0f0", borderTop: "1px solid #ddd" }}>
        <div
          className="wphfc-flex-1 wphfc-rounded-full wphfc-px-3 wphfc-py-1"
          style={{ background: "white", fontSize: "12px", color: "#aaa" }}>
          Type a message
        </div>
        <div
          className="wphfc-flex wphfc-items-center wphfc-justify-center wphfc-rounded-full wphfc-shrink-0"
          style={{ width: "32px", height: "32px", background: "#25d366" }}>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
            <path d="M2 21l21-9L2 3v7l15 2-15 2v7z" />
          </svg>
        </div>
      </div>
    </div>
  );
};

export default WPHFC_WhatsappPreview;

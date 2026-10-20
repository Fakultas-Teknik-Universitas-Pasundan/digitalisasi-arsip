import axios from "axios";

// In local development the dashboard can be opened via `localhost` OR
// `127.0.0.1`. Cookies (such as the XSRF token read by the request interceptor)
// are host-scoped, so the API must be called on the SAME hostname as the page.
// `NEXT_PUBLIC_API_URL` still takes precedence when provided.
const defaultBaseUrl =
  typeof window !== "undefined"
    ? `${window.location.protocol}//${window.location.hostname}:8000`
    : "http://localhost:8000";

export const http = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL ?? defaultBaseUrl,
  withCredentials: true,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
  },
});

http.interceptors.request.use(async (config) => {
  if (config.data instanceof FormData) {
    delete config.headers["Content-Type"];
  }

  if (typeof window === "undefined") {
    try {
      const { cookies } = await import("next/headers");
      const cookieStore = await cookies();
      const cookieString = cookieStore.toString();
      if (cookieString) {
        config.headers.Cookie = cookieString;
      }
    } catch (error) {
      if (process.env.NODE_ENV !== "production") {
        console.warn(
          "Request cookies unavailable in current execution context:",
          error,
        );
      }
    }
  } else {
    const xsrfToken = document.cookie
      .split("; ")
      .find((row) => row.startsWith("XSRF-TOKEN="))
      ?.split("=")[1];
    if (xsrfToken) {
      config.headers["X-XSRF-TOKEN"] = decodeURIComponent(xsrfToken);
    }
  }
  return config;
});

http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401 && typeof window !== "undefined") {
      if (window.location.pathname !== "/login") {
        window.location.href = "/login";
      }
    }

    if (process.env.NODE_ENV !== "production") {
      console.error("HTTP Error:", error);
    }

    return Promise.reject(error);
  },
);

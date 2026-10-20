import { http } from "@/shared/lib/http";
import { AuthUser, LoginCredentials, LoginResponse } from "../types/auth.types";

export const authService = {
  async csrfCookie(): Promise<void> {
    await http.get("/sanctum/csrf-cookie");
  },

  async login(credentials: LoginCredentials): Promise<LoginResponse> {
    await this.csrfCookie();
    const res = await http.post<LoginResponse>("/api/auth/login", credentials);
    return res.data;
  },

  async logout(): Promise<{ message: string }> {
    try {
      const res = await http.post<{ message: string }>("/api/auth/logout");
      return res.data;
    } catch {
      return { message: "Logged out" };
    }
  },

  async me(): Promise<AuthUser | null> {
    try {
      const res = await http.get<{ data: AuthUser }>("/api/auth/me");
      return (
        res.data?.data ??
        (res.data as unknown as { user?: AuthUser })?.user ??
        null
      );
    } catch (err: unknown) {
      const status = (err as { response?: { status?: number } })?.response
        ?.status;
      // No HTTP status means the request never reached the API (network/CORS/
      // timeout). Treat it as "not authenticated" so the auth guards resolve and
      // show the login page instead of spinning on the auth check forever.
      if (!status || status === 401 || status === 419) {
        return null;
      }
      throw err;
    }
  },
};

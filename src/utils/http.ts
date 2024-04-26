import ApiResponse from "~/interfaces/apiResponse";

export async function get<T>(url: string, frontend: boolean = false): Promise<T> {

	const fullUrl = (frontend? import.meta.env.PUBLIC_BACKENND_API_URL : import.meta.env.PUBLIC_FRONTEND_API_URL) + url;

	const response = await fetch(fullUrl);
	const apiResponse: ApiResponse<T> = await response.json();

	if (!apiResponse.success) {
		throw new Error('API request failed');
	}

	return apiResponse.data;
}
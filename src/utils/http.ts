import ApiResponse from "~/interfaces/apiResponse";


export async function get<T>(url: string, baseUrl?: string, parameters?: Record<string, string>): Promise<T | null> {
	baseUrl = baseUrl ?? import.meta.env.PUBLIC_FRONTEND_API_URL as string;
	const fullUrl = new URL(url, baseUrl);

	// Append provided parameters to the URL
	if (parameters) {
		Object.entries(parameters).forEach(([key, value]) => {
			fullUrl.searchParams.set(key, value);
		});
	}

	// Check if the request is client-side and append current URL's query parameters if so
	if (!baseUrl && typeof window !== 'undefined') {
		const currentSearchParams = new URLSearchParams(window.location.search);
		currentSearchParams.forEach((value, key) => {
			fullUrl.searchParams.append(key, value);
		});
	}

	try {
		const response = await fetch(fullUrl.toString());

		if (!response.ok) {
			const errorDetails = {
				status: response.status,
				statusText: response.statusText,
				url: fullUrl.toString(),
				body: await response.text()
			};

			console.error("API request failed:", errorDetails);
			throw new Error(`API request failed: ${response.statusText} (Status: ${response.status})`);
		}

		const apiResponse: ApiResponse<T> = await response.json();

		if (!apiResponse.success) {
			console.error("API request failed with business logic failure:", apiResponse);
			throw new Error(`API request failed: Business logic error. See console for details.`);
		}

		return apiResponse.data;
	} catch (error) {
		console.error("Error fetching the API:", error);
		return null;
	}
}
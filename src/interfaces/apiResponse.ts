export default interface ApiResponse<T> {
	success: boolean;
	data: T;
}
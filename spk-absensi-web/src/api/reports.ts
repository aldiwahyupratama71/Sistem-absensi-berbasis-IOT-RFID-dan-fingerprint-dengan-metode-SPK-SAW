import axiosInstance from './axiosInstance'

export const reportApi = {
  exportEvaluations: async (periodId: number, type: 'pdf' | 'excel') => {
    const format = type === 'excel' ? 'xlsx' : 'pdf';
    const response = await axiosInstance.get(`/evaluation-periods/${periodId}/export`, {
      params: { format },
      responseType: 'blob'
    })
    return response
  }
}

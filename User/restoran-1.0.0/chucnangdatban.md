Chức Năng Đặt Bàn Với Phụ Phí Khu Vực

Bước 1: Truy Cập Và Điền Thông Tin Ban Đầu





Khách hàng truy cập trang chủ và chọn "Đặt Bàn".



Hệ thống hiển thị form thông tin:





Số lượng khách hàng.



Thời gian đặt bàn (ngày, giờ).



Khu vực mong muốn (tùy chọn: Tầng 1: 0đ, Tầng 2: 50.000đ/bàn, Phòng VIP: 200.000đ/bàn, Sân vườn: 100.000đ/bàn – hiển thị phụ phí để tham khảo).



Xử Lý Lỗi:





Số lượng khách phải là số nguyên dương, phù hợp với sức chứa khu vực.



Thời gian đặt bàn phải trong khung giờ hoạt động (ví dụ: 10:00–22:00) và không trùng với đặt chỗ khác.



Khu vực mong muốn phải khả dụng; nếu không, thông báo lỗi (ví dụ: "Sân vườn không khả dụng vào giờ này").



Hiển thị thông báo lỗi cụ thể (ví dụ: "Vui lòng chọn thời gian trong giờ mở cửa").



Khách hàng điền đầy đủ và nhấn "Xác Nhận". Hệ thống kiểm tra tính khả dụng (bao gồm phụ phí khu vực) và chuyển sang trang chọn bàn.

Bước 2: Chọn Bàn





Hiển thị danh sách khu vực khả dụng với sơ đồ bàn (trạng thái: trống, đã đặt), kèm phụ phí rõ ràng:





Tầng 1: 0đ/bàn.



Tầng 2: 50.000đ/bàn.



Phòng VIP: 200.000đ/bàn.



Sân vườn: 100.000đ/bàn.



Khách hàng chọn khu vực và có thể chọn nhiều bàn (phù hợp với số lượng khách).



Xử Lý Lỗi:





Kiểm tra trùng lặp bàn: Đảm bảo bàn không bị đặt bởi khách khác trong cùng khung giờ.



Kiểm tra sức chứa: Tổng sức chứa của bàn phải đủ cho số lượng khách (ví dụ: Phòng VIP yêu cầu tối thiểu 4 người).



Kiểm tra phụ phí: Tính tổng phụ phí (ví dụ: 2 bàn ở Tầng 2 = 2 × 50.000đ = 100.000đ). Thông báo nếu chọn khu vực không phù hợp (ví dụ: "Phòng VIP yêu cầu tối thiểu 4 khách").



Thông báo lỗi nếu chọn bàn không hợp lệ (ví dụ: "Bàn này đã được đặt" hoặc "Phụ phí Phòng VIP: 200.000đ/bàn – Vui lòng xác nhận").



Sau khi chọn xong (hiển thị tổng phụ phí sơ bộ), khách hàng nhấn "Xác Nhận Đặt Bàn".



Hệ thống hỏi: "Bạn có muốn đặt món ăn kèm không?" (tùy chọn: Có/Không).

Luồng 1: Không Đặt Món (Chuyển Sang Xác Nhận)





Nếu chọn "Không", hệ thống chuyển sang trang xác nhận, hiển thị:





Tên, số điện thoại, email khách hàng.



Giờ đặt, số lượng khách, thời gian sử dụng.



Danh sách bàn đã chọn (khu vực và phụ phí, ví dụ: "Tầng 2, Bàn A1, A2 – Phụ phí: 100.000đ").



Phương thức thanh toán (chuyển khoản, thẻ tín dụng, ví điện tử).



Tổng giá trị: Phụ phí khu vực (ví dụ: 100.000đ cho 2 bàn Tầng 2).



Xử Lý Lỗi: Kiểm tra thông tin khách hàng (email hợp lệ, số điện thoại đúng định dạng) và tính toán phụ phí. Yêu cầu chỉnh sửa nếu lỗi (ví dụ: "Email không hợp lệ").



Khách hàng chọn phương thức thanh toán, nhấn "Xác Nhận" để chuyển sang trang thanh toán.



Trang thanh toán hiển thị mã QR và thông báo: "Bàn của bạn được giữ trong 12 giờ để chờ thanh toán (bao gồm phụ phí khu vực). Sau thời gian này, đơn hàng sẽ tự động bị hủy."



Thanh Toán Thành Công:





Gửi email xác nhận kèm file PDF chứa thông tin đặt bàn (bao gồm phụ phí, ví dụ: "Phụ phí Sân vườn: 100.000đ/bàn"), tổng hóa đơn, và mã QR check-in.



Mã QR mã hóa thông tin đơn hàng (tên khách, giờ đặt, bàn, khu vực, phụ phí), cho phép nhân viên quét để xác nhận.



Hết Hạn Thanh Toán:





Sau 12 giờ, hệ thống hủy đơn, cập nhật trạng thái bàn thành trống, xóa đơn khỏi cơ sở dữ liệu.

Luồng 2: Đặt Món Kèm (Chuyển Sang Trang Chọn Món)





Nếu chọn "Có", hệ thống chuyển sang trang chọn món với hai tùy chọn:





Chọn theo từng món: Hiển thị danh sách món với nút "+" để thêm, "-" để xóa. Hỗ trợ tìm kiếm bằng từ khóa hoặc lọc theo danh mục (món khai vị, món chính, đồ uống).



Chọn theo thực đơn: Hiển thị thực đơn gợi ý dựa trên số lượng khách (ví dụ: thực đơn cho 2-4 người). Hỗ trợ chỉnh sửa món.



Xử Lý Lỗi:





Kiểm tra tính khả dụng của món (món hết hàng bị vô hiệu hóa).



Giới hạn số lượng món phù hợp với số khách (thông báo nếu chọn quá nhiều).



Tìm kiếm từ khóa trả kết quả chính xác, thông báo "Không tìm thấy món" nếu không có.



Sau khi chọn món, hệ thống chuyển sang trang xác nhận (bao gồm thông tin đặt bàn, món ăn, phụ phí khu vực, tổng giá trị: phụ phí + giá món ăn).



Khách hàng chọn phương thức thanh toán, nhấn "Xác Nhận" để chuyển sang trang thanh toán.



Quy trình thanh toán tương tự Luồng 1, với mã QR, email/PDF xác nhận (bao gồm phụ phí) và mã QR check-in.

Tích Hợp Phụ Phí Khu Vực





Xuất PDF: File PDF chứa chi tiết phụ phí (ví dụ: "Phụ phí Phòng VIP: 200.000đ x 1 bàn = 200.000đ"), tổng hóa đơn, và mã QR check-in.



Mã QR Check-in: Mã hóa thông tin đơn hàng (bao gồm phụ phí) để nhân viên xác nhận chính xác khu vực và chi phí.



Xử Lý Lỗi Và Bảo Mật:





Phụ phí được tính động từ cơ sở dữ liệu, kiểm tra lỗi nếu khu vực không khả dụng hoặc phụ phí không khớp.



Bảo mật: Tổng hóa đơn (bao gồm phụ phí) được mã hóa trong mã QR và truyền tải qua HTTPS.



Đơn hàng hết hạn (sau 12 giờ) được xóa khỏi cơ sở dữ liệu, chỉ lưu log tối thiểu để tối ưu hóa.



Lợi Ích: Phụ phí minh bạch, tăng doanh thu từ khu vực cao cấp (Phòng VIP, Sân vườn), đồng thời giúp khách hàng dễ dàng so sánh và chọn lựa.
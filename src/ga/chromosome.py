import copy

class Gene:
    """
    Đại diện cho một Gene trong cấu trúc Nhiễm sắc thể (Chromosome).
    Mỗi Gene quản lý việc phân bổ lịch thi của đúng MỘT LỚP HỌC PHẦN (Course Section).
    
    Cấu trúc tuân thủ nghiêm ngặt công thức thiết kế: Gene_i = (c_i, t_k, R_selected)
    Đảm bảo hoàn toàn không chứa trường dữ liệu liên quan đến MSSV hay thông tin sinh viên.
    """
    def __init__(self, section_id, timeslot_id, room_ids):
        """
        Khởi tạo một Gene hoàn chỉnh cho một lớp học phần.
        
        Parameters:
        -----------
        section_id : str hoặc int
            c_i: Định danh hoặc mã của lớp học phần cần xếp lịch.
        timeslot_id : str hoặc int
            t_k: Ca thi được hệ thống chỉ định (chứa thông tin ngày thi và khung giờ thi).
        room_ids : list hoặc set
            R_selected: Tập hợp một hoặc nhiều mã phòng thi được cấp phát gộp để đáp ứng đủ sĩ số.
        """
        self.section_id = section_id
        self.timeslot_id = timeslot_id
        # Đảm bảo room_ids luôn lưu trữ dưới dạng một danh sách các phòng được chọn (R_selected)
        self.room_ids = list(room_ids) if isinstance(room_ids, (list, set, tuple)) else [room_ids]

    def __repr__(self):
        return f"Gene(Section: {self.section_id}, Timeslot: {self.timeslot_id}, Rooms: {self.room_ids})"

    def clone(self):
        """Tạo một bản sao độc lập của Gene, phục vụ cho các toán tử biến đổi di truyền."""
        return Gene(self.section_id, self.timeslot_id, list(self.room_ids))


class Chromosome:
    """
    Đại diện cho một Nhiễm sắc thể (Cá thể) trong Quần thể của Thuật toán di truyền (GA).
    Mỗi Nhiễm sắc thể đại diện cho một phương án lập lịch thi TOÀN TRƯỜNG hoàn chỉnh.
    
    Cấu trúc nhiễm sắc thể là một vector chứa n Gene (với n là tổng số lớp học phần của học kỳ).
    """
    def __init__(self, genes=None):
        """
        Khởi tạo một Nhiễm sắc thể.
        
        Parameters:
        -----------
        genes : list, optional
            Danh sách các đối tượng Gene cấu thành. Mặc định là None (khởi tạo nhiễm sắc thể rỗng).
        """
        self.genes = genes if genes is not None else []
        self.fitness = 0.0  # Chỉ số độ thích nghi của phương án, dao động trong khoảng (0, 1]

    def add_gene(self, gene):
        """Thêm một Gene mới (lịch của một lớp học phần) vào nhiễm sắc thể."""
        if isinstance(gene, Gene):
            self.genes.append(gene)
        else:
            raise TypeError("Đối tượng thêm vào phải là một thực thể thuộc lớp Gene.")

    def get_gene_by_section(self, section_id):
        """Tìm kiếm nhanh một Gene trong phương án dựa trên mã lớp học phần."""
        for gene in self.genes:
            if gene.section_id == section_id:
                return gene
        return None

    def clone(self):
        """
        Tạo một bản sao sâu (Deep Copy) của toàn bộ Nhiễm sắc thể.
        Hàm này cực kỳ quan trọng nhằm cô lập dữ liệu của cá thể cha mẹ khi thực hiện
        các toán tử lai ghép (Crossover) và đột biến (Mutation) để tránh lỗi ghi đè vùng nhớ.
        """
        cloned_chromosome = Chromosome()
        cloned_chromosome.genes = [gene.clone() for gene in self.genes]
        cloned_chromosome.fitness = self.fitness
        return cloned_chromosome

    def __len__(self):
        """Trả về số lượng Gene bên trong nhiễm sắc thể (tổng số lớp học phần đã được sắp xếp)."""
        return len(self.genes)

    def __repr__(self):
        return f"Chromosome(Total_Genes: {len(self.genes)}, Fitness: {self.fitness:.6f})"
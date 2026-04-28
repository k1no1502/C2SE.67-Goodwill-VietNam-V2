<?php
/**
 * Tạo file TrainingAI.xlsx
 * Sheet 1: Từ ngữ (Text Moderation Training Data)
 * Sheet 2: Ảnh (Image Moderation Training Data)
 * 
 * Dùng ZipArchive + raw Open XML - KHÔNG cần thư viện bên ngoài.
 */

$outputPath = __DIR__ . '/TrainingAI.xlsx';
if (file_exists($outputPath)) unlink($outputPath);

// ============================================================
// DATA
// ============================================================

$toxicWords = [
    [1, 'cặc', 'TOXIC', 'Tục tĩu - Bộ phận cơ thể', 'cac, cặk, cak, kặc, kak, c.ặ.c, c*c', 'BỊ CHẶN', 'Từ chỉ bộ phận sinh dục nam'],
    [2, 'lồn', 'TOXIC', 'Tục tĩu - Bộ phận cơ thể', 'lon, loz, l0n, l.ồ.n, l*n', 'BỊ CHẶN', 'Từ chỉ bộ phận sinh dục nữ'],
    [3, 'buồi', 'TOXIC', 'Tục tĩu - Bộ phận cơ thể', 'buoi, b.u.ồ.i, bu0i', 'BỊ CHẶN', 'Từ chỉ bộ phận sinh dục nam'],
    [4, 'dái', 'TOXIC', 'Tục tĩu - Bộ phận cơ thể', 'd.á.i, d@á@i, d$á$i', 'BỊ CHẶN', 'Bộ phận sinh dục'],
    [5, 'đít', 'TOXIC', 'Tục tĩu - Bộ phận cơ thể', 'dit, đ.í.t, d!t', 'BỊ CHẶN', 'Vùng mông (ngữ cảnh tục)'],
    [6, 'cứt', 'TOXIC', 'Tục tĩu - Chất thải', 'cut, c.ứ.t, c@ứ@t, c$ứ$t', 'BỊ CHẶN', 'Chất thải'],
    [7, 'đụ', 'TOXIC', 'Chửi thề - Hành động', 'du, đ.ụ, d@u', 'BỊ CHẶN', 'Chửi thề phổ biến miền Nam'],
    [8, 'đụ má', 'TOXIC', 'Chửi thề - Hành động', 'du má, du ma, đ.ụ m.á, d@u m@a', 'BỊ CHẶN', 'Chửi thề nặng'],
    [9, 'địt', 'TOXIC', 'Chửi thề - Hành động', 'dit, đ.ị.t, đ*t, đ!t, đ@t, đ$t, đ%t', 'BỊ CHẶN', 'Chửi thề phổ biến miền Bắc'],
    [10, 'địt mẹ', 'TOXIC', 'Chửi thề - Hành động', 'dit me, đ.m, đm, d!t m3', 'BỊ CHẶN', 'Chửi thề rất nặng'],
    [11, 'chịch', 'TOXIC', 'Chửi thề - Hành động', 'chich, ch.ị.ch, ch!ch', 'BỊ CHẶN', 'Gợi dục'],
    [12, 'đm', 'TOXIC', 'Viết tắt chửi thề', 'đ.m, d.m, dm, d@m, d$m', 'BỊ CHẶN', 'Viết tắt "địt mẹ"'],
    [13, 'đkm', 'TOXIC', 'Viết tắt chửi thề', 'dkm, đ.k.m, d.k.m, d@k@m', 'BỊ CHẶN', 'Viết tắt "địt con mẹ"'],
    [14, 'đcm', 'TOXIC', 'Viết tắt chửi thề', 'dcm, đ.c.m, d.c.m, d@c@m', 'BỊ CHẶN', 'Viết tắt "địt con mẹ"'],
    [15, 'đmm', 'TOXIC', 'Viết tắt chửi thề', 'dmm, đ.m.m, d@m@m', 'BỊ CHẶN', 'Viết tắt chửi thề'],
    [16, 'vcl', 'TOXIC', 'Viết tắt chửi thề', 'v.c.l, v*c*l, vkl, v@c@l, v$c$l', 'BỊ CHẶN', 'Viết tắt "vãi cặc lồn"'],
    [17, 'vl', 'TOXIC', 'Viết tắt chửi thề', 'v.l, v*l, v@l, v$l', 'BỊ CHẶN', 'Viết tắt "vãi lồn"'],
    [18, 'clm', 'TOXIC', 'Viết tắt chửi thề', 'c.l.m, cl, c@l@m', 'BỊ CHẶN', 'Viết tắt "con lồn mẹ"'],
    [19, 'wtf', 'TOXIC', 'Viết tắt chửi thề (Anh)', 'w.t.f, w@t@f', 'BỊ CHẶN', 'Tiếng Anh'],
    [20, 'con cặc', 'TOXIC', 'Cụm từ tục tĩu', 'con cac, con kặc, con c.ặ.c, con c@c', 'BỊ CHẶN', 'Cụm từ chửi thề'],
    [21, 'con lồn', 'TOXIC', 'Cụm từ tục tĩu', 'con lon, con l.ồ.n, con l@n', 'BỊ CHẶN', 'Cụm từ chửi thề'],
    [22, 'vãi lồn', 'TOXIC', 'Cụm từ tục tĩu', 'vai lon, vãi l.ồ.n, vai l@n', 'BỊ CHẶN', 'Cụm từ chửi thề'],
    [23, 'vãi cặc', 'TOXIC', 'Cụm từ tục tĩu', 'vai cac, vãi c.ặ.c, vai c@c', 'BỊ CHẶN', 'Cụm từ chửi thề'],
    [24, 'đồ chó', 'TOXIC', 'Sỉ nhục', 'do cho, đồ ch.ó', 'BỊ CHẶN', 'Xúc phạm nhân phẩm'],
    [25, 'thằng chó', 'TOXIC', 'Sỉ nhục', 'thang cho, thằng ch@', 'BỊ CHẶN', 'Xúc phạm nhân phẩm'],
    [26, 'con chó', 'TOXIC', 'Sỉ nhục', '', 'BỊ CHẶN', 'Xúc phạm (ngữ cảnh chửi)'],
    [27, 'mẹ mày', 'TOXIC', 'Sỉ nhục', 'me may, m.ẹ m.à.y', 'BỊ CHẶN', 'Chửi thề nhắm mục tiêu'],
    [28, 'má mày', 'TOXIC', 'Sỉ nhục', 'ma may, m.á m.à.y', 'BỊ CHẶN', 'Chửi thề nhắm mục tiêu'],
    [29, 'thằng ngu', 'TOXIC', 'Sỉ nhục', 'thang ngu, thằng ng@', 'BỊ CHẶN', 'Xúc phạm trí tuệ'],
    [30, 'con ngu', 'TOXIC', 'Sỉ nhục', '', 'BỊ CHẶN', 'Xúc phạm trí tuệ'],
    [31, 'đồ ngu', 'TOXIC', 'Sỉ nhục', 'do ngu', 'BỊ CHẶN', 'Xúc phạm trí tuệ'],
    [32, 'óc chó', 'TOXIC', 'Sỉ nhục', 'oc cho, óc ch.ó', 'BỊ CHẶN', 'Xúc phạm trí tuệ'],
    [33, 'đéo', 'TOXIC', 'Khiếm nhã', 'deo, đ.é.o, d@e@o', 'BỊ CHẶN', 'Từ phủ định thô tục'],
    [34, 'đếch', 'TOXIC', 'Khiếm nhã', 'dech, đ.ế.ch', 'BỊ CHẶN', 'Từ phủ định thô tục'],
    [35, 'đĩ', 'TOXIC', 'Khiếm nhã', 'di~, đ.ĩ, d!', 'BỊ CHẶN', 'Xúc phạm phụ nữ'],
    [36, 'vãi', 'TOXIC', 'Khiếm nhã', 'vai', 'BỊ CHẶN', 'Thán từ thô tục'],
    [37, 'ngu', 'TOXIC', 'Khiếm nhã', '', 'BỊ CHẶN', 'Xúc phạm (ngữ cảnh phụ thuộc)'],
    [38, 'fuck', 'TOXIC', 'Tục tĩu (Anh)', 'f*ck, fck, fuk, f.u.c.k, f@ck, f$ck, phuck', 'BỊ CHẶN', 'Chửi thề tiếng Anh'],
    [39, 'shit', 'TOXIC', 'Tục tĩu (Anh)', 'sh*t, sh!t, s.h.i.t, sh@t, $hit', 'BỊ CHẶN', 'Chửi thề tiếng Anh'],
    [40, 'bitch', 'TOXIC', 'Tục tĩu (Anh)', 'b*tch, b!tch, b1tch, bi+ch', 'BỊ CHẶN', 'Xúc phạm tiếng Anh'],
    [41, 'dick', 'TOXIC', 'Tục tĩu (Anh)', 'd*ck, d!ck, d1ck, d@ck', 'BỊ CHẶN', 'Bộ phận cơ thể (Anh)'],
    [42, 'pussy', 'TOXIC', 'Tục tĩu (Anh)', 'p*ssy, pu$$y, pu$sy', 'BỊ CHẶN', 'Bộ phận cơ thể (Anh)'],
    [43, 'ass', 'TOXIC', 'Tục tĩu (Anh)', 'a$$, @ss, a$s, @$$', 'BỊ CHẶN', 'Bộ phận cơ thể (Anh)'],
    [44, 'porn', 'TOXIC', 'Gợi dục (Anh)', 'p0rn, pr0n, p@rn', 'BỊ CHẶN', 'Nội dung khiêu dâm'],
    [45, 'sexy', 'TOXIC', 'Gợi dục (Anh)', 'sexxy, s3xy, s€xy', 'BỊ CHẶN', 'Gợi dục'],

    // === BYPASS với ký tự đặc biệt (@ $ # % ^ & ! ~ = +) ===
    [46, 'c@c', 'TOXIC', 'Bypass - Dùng @', 'c@ặ@c, c@@c', 'BỊ CHẶN', 'Bypass cặc bằng @'],
    [47, 'c$c', 'TOXIC', 'Bypass - Dùng $', 'c$ặ$c, c$$c', 'BỊ CHẶN', 'Bypass cặc bằng $'],
    [48, 'c#c', 'TOXIC', 'Bypass - Dùng #', 'c#ặ#c, c##c', 'BỊ CHẶN', 'Bypass cặc bằng #'],
    [49, 'c%c', 'TOXIC', 'Bypass - Dùng %', 'c%ặ%c, c%%c', 'BỊ CHẶN', 'Bypass cặc bằng %'],
    [50, 'c&c', 'TOXIC', 'Bypass - Dùng &', 'c&ặ&c', 'BỊ CHẶN', 'Bypass cặc bằng &'],
    [51, 'c^c', 'TOXIC', 'Bypass - Dùng ^', 'c^ặ^c', 'BỊ CHẶN', 'Bypass cặc bằng ^'],
    [52, 'c!c', 'TOXIC', 'Bypass - Dùng !', 'c!ặ!c', 'BỊ CHẶN', 'Bypass cặc bằng !'],
    [53, 'c~c', 'TOXIC', 'Bypass - Dùng ~', 'c~ặ~c', 'BỊ CHẶN', 'Bypass cặc bằng ~'],
    [54, 'c=c', 'TOXIC', 'Bypass - Dùng =', 'c=ặ=c', 'BỊ CHẶN', 'Bypass cặc bằng ='],
    [55, 'c+c', 'TOXIC', 'Bypass - Dùng +', 'c+ặ+c', 'BỊ CHẶN', 'Bypass cặc bằng +'],
    [56, 'l@n', 'TOXIC', 'Bypass - Dùng @', 'l@ồ@n, l@@n', 'BỊ CHẶN', 'Bypass lồn bằng @'],
    [57, 'l$n', 'TOXIC', 'Bypass - Dùng $', 'l$ồ$n, l$$n', 'BỊ CHẶN', 'Bypass lồn bằng $'],
    [58, 'l#n', 'TOXIC', 'Bypass - Dùng #', 'l#ồ#n', 'BỊ CHẶN', 'Bypass lồn bằng #'],
    [59, 'đ@t', 'TOXIC', 'Bypass - Dùng @', 'đ@ị@t, d@i@t', 'BỊ CHẶN', 'Bypass địt bằng @'],
    [60, 'đ$t', 'TOXIC', 'Bypass - Dùng $', 'đ$ị$t, d$i$t', 'BỊ CHẶN', 'Bypass địt bằng $'],
    [61, 'đ#t', 'TOXIC', 'Bypass - Dùng #', 'đ#ị#t, d#i#t', 'BỊ CHẶN', 'Bypass địt bằng #'],

    // === Bypass dùng số thay chữ ===
    [62, 'c4c', 'TOXIC', 'Bypass - Dùng số', 'Thay a bằng 4', 'BỊ CHẶN', 'Leetspeak: 4=a'],
    [63, 'l0n', 'TOXIC', 'Bypass - Dùng số', 'Thay o bằng 0', 'BỊ CHẶN', 'Leetspeak: 0=o'],
    [64, 'd1t', 'TOXIC', 'Bypass - Dùng số', 'Thay i bằng 1', 'BỊ CHẶN', 'Leetspeak: 1=i'],
    [65, 'bu01', 'TOXIC', 'Bypass - Dùng số', 'bu0i, bu01', 'BỊ CHẶN', 'Leetspeak: 0=o, 1=i'],
    [66, 'fck', 'TOXIC', 'Bypass - Bỏ nguyên âm', 'fk, fkk', 'BỊ CHẶN', 'Bỏ nguyên âm để bypass'],
    [67, 'sht', 'TOXIC', 'Bypass - Bỏ nguyên âm', 'sh1t, $h1t', 'BỊ CHẶN', 'Bỏ nguyên âm để bypass'],
    [68, 'b!tch', 'TOXIC', 'Bypass - Dùng !', 'b!7ch, bi7ch', 'BỊ CHẶN', 'Bypass bằng ! thay i'],
    [69, '$hit', 'TOXIC', 'Bypass - Dùng $', '$h!t, $h1t', 'BỊ CHẶN', 'Bypass bằng $ thay s'],
    [70, 'phuck', 'TOXIC', 'Bypass - Đổi âm', 'phuk, phk, ph@ck', 'BỊ CHẶN', 'Đổi f thành ph'],

    // === Thêm từ tục tiếng Việt (bổ sung) ===
    [71, 'đĩ điếm', 'TOXIC', 'Sỉ nhục', 'di diem, đ.ĩ đ.iếm', 'BỊ CHẶN', 'Xúc phạm phụ nữ nặng'],
    [72, 'chó đẻ', 'TOXIC', 'Sỉ nhục', 'cho de, ch.ó đ.ẻ', 'BỊ CHẶN', 'Xúc phạm nặng'],
    [73, 'đồ điên', 'TOXIC', 'Sỉ nhục', 'do dien', 'BỊ CHẶN', 'Xúc phạm tinh thần'],
    [74, 'thằng điên', 'TOXIC', 'Sỉ nhục', 'thang dien', 'BỊ CHẶN', 'Xúc phạm tinh thần'],
    [75, 'con điên', 'TOXIC', 'Sỉ nhục', '', 'BỊ CHẶN', 'Xúc phạm tinh thần'],
    [76, 'khốn nạn', 'TOXIC', 'Sỉ nhục', 'khon nan, kh.ốn n.ạn', 'BỊ CHẶN', 'Sỉ nhục nhân phẩm'],
    [77, 'mặt lồn', 'TOXIC', 'Cụm từ tục tĩu', 'mat lon, m.ặt l.ồn', 'BỊ CHẶN', 'Cụm từ tục tĩu'],
    [78, 'mặt cặc', 'TOXIC', 'Cụm từ tục tĩu', 'mat cac, m.ặt c.ặc', 'BỊ CHẶN', 'Cụm từ tục tĩu'],
    [79, 'đồ khốn', 'TOXIC', 'Sỉ nhục', 'do khon', 'BỊ CHẶN', 'Sỉ nhục nhân phẩm'],
    [80, 'chết mẹ', 'TOXIC', 'Khiếm nhã', 'chet me, ch.ết m.ẹ', 'BỊ CHẶN', 'Chửi thề'],
    [81, 'đồ rác', 'TOXIC', 'Sỉ nhục', 'do rac', 'BỊ CHẶN', 'Sỉ nhục nhân phẩm'],
    [82, 'thằng khốn', 'TOXIC', 'Sỉ nhục', 'thang khon', 'BỊ CHẶN', 'Sỉ nhục nhân phẩm'],
    [83, 'con đĩ', 'TOXIC', 'Sỉ nhục', 'con di, con đ.ĩ', 'BỊ CHẶN', 'Xúc phạm phụ nữ nặng'],
    [84, 'thằng khùng', 'TOXIC', 'Sỉ nhục', 'thang khung', 'BỊ CHẶN', 'Xúc phạm tinh thần'],
    [85, 'đồ nát', 'TOXIC', 'Sỉ nhục', 'do nat', 'BỊ CHẶN', 'Sỉ nhục'],
    [86, 'dâm dục', 'TOXIC', 'Gợi dục', 'dam duc, d.âm d.ục', 'BỊ CHẶN', 'Nội dung gợi dục'],
    [87, 'đụ mẹ', 'TOXIC', 'Chửi thề - Hành động', 'du me, đ.ụ m.ẹ, d@u m@e', 'BỊ CHẶN', 'Chửi thề nặng (miền Bắc)'],
    [88, 'cái lồn', 'TOXIC', 'Cụm từ tục tĩu', 'cai lon, c.ái l.ồn', 'BỊ CHẶN', 'Cụm từ tục tĩu'],
    [89, 'cái cặc', 'TOXIC', 'Cụm từ tục tĩu', 'cai cac, c.ái c.ặc', 'BỊ CHẶN', 'Cụm từ tục tĩu'],

    // === Bypass dùng ký tự Unicode giống ===
    [90, 'Dùng chữ Cyrillic', 'TOXIC', 'Bypass - Unicode', 'а→a, с→c, о→o (Cyrillic giống Latin)', 'BỊ CHẶN', 'Thay chữ cái bằng Unicode giống hệt'],
    [91, 'Dùng fullwidth', 'TOXIC', 'Bypass - Unicode', 'ｃ→c, ａ→a (chữ rộng)', 'BỊ CHẶN', 'Thay chữ cái bằng fullwidth Unicode'],
    [92, 'Dùng dấu kết hợp', 'TOXIC', 'Bypass - Unicode', 'c̃ặ̃c̃ (thêm dấu ngã lên trên)', 'BỊ CHẶN', 'Thêm combining marks vào chữ'],

    // === Tiếng Anh - Chửi thề cơ bản (English Profanity) ===
    [93, 'motherfucker', 'TOXIC', 'Tục tĩu nặng (Anh)', 'motherf*cker, mf, m0therf, mothafucka', 'BỊ CHẶN', 'Chửi thề rất nặng'],
    [94, 'son of a bitch', 'TOXIC', 'Tục tĩu (Anh)', 'sob, soab, son of a b*tch', 'BỊ CHẶN', 'Chửi thề nặng'],
    [95, 'asshole', 'TOXIC', 'Tục tĩu (Anh)', 'a$$hole, @sshole, a-hole', 'BỊ CHẶN', 'Chửi thề'],
    [96, 'bastard', 'TOXIC', 'Tục tĩu (Anh)', 'b@stard, bast@rd, b4stard', 'BỊ CHẶN', 'Chửi thề'],
    [97, 'bullshit', 'TOXIC', 'Tục tĩu (Anh)', 'b.s, bs, bull$hit, bullsh!t', 'BỊ CHẶN', 'Chửi thề'],
    [98, 'dumbass', 'TOXIC', 'Sỉ nhục (Anh)', 'dumb@ss, dumba$$, dumb4ss', 'BỊ CHẶN', 'Xúc phạm trí tuệ'],
    [99, 'jackass', 'TOXIC', 'Sỉ nhục (Anh)', 'jack@ss, jacka$$', 'BỊ CHẶN', 'Xúc phạm'],
    [100, 'dipshit', 'TOXIC', 'Tục tĩu (Anh)', 'dip$hit, dipsh!t', 'BỊ CHẶN', 'Chửi thề'],
    [101, 'douchebag', 'TOXIC', 'Sỉ nhục (Anh)', 'douche, d0uchebag, doucheb@g', 'BỊ CHẶN', 'Xúc phạm'],
    [102, 'prick', 'TOXIC', 'Tục tĩu (Anh)', 'pr!ck, pr1ck', 'BỊ CHẶN', 'Tục tĩu / sỉ nhục'],
    [103, 'twat', 'TOXIC', 'Tục tĩu (Anh)', 'tw@t, tw4t', 'BỊ CHẶN', 'Tục tĩu nặng (Anh-Anh)'],
    [104, 'cock', 'TOXIC', 'Tục tĩu (Anh)', 'c0ck, c@ck, c*ck', 'BỊ CHẶN', 'Bộ phận cơ thể'],
    [105, 'bollocks', 'TOXIC', 'Tục tĩu (Anh)', 'boll0cks, b0llocks', 'BỊ CHẶN', 'Chửi thề (Anh-Anh)'],
    [106, 'bugger', 'TOXIC', 'Tục tĩu (Anh)', 'bugg3r, b*gger', 'BỊ CHẶN', 'Chửi thề (Anh-Anh)'],
    [107, 'crap', 'TOXIC', 'Khiếm nhã (Anh)', 'cr@p, cr4p', 'BỊ CHẶN', 'Chửi thề nhẹ'],
    [108, 'damn', 'TOXIC', 'Khiếm nhã (Anh)', 'd@mn, d4mn, dammit, goddamn', 'BỊ CHẶN', 'Chửi thề nhẹ'],
    [109, 'hell', 'TOXIC', 'Khiếm nhã (Anh)', 'h3ll, h€ll', 'BỊ CHẶN', 'Chửi thề nhẹ (ngữ cảnh)'],
    [110, 'stfu', 'TOXIC', 'Viết tắt chửi thề (Anh)', 'STFU, s.t.f.u, $tfu', 'BỊ CHẶN', 'Shut the fuck up'],
    [111, 'gtfo', 'TOXIC', 'Viết tắt chửi thề (Anh)', 'GTFO, g.t.f.o, gtf0', 'BỊ CHẶN', 'Get the fuck out'],
    [112, 'lmfao', 'TOXIC', 'Viết tắt chửi thề (Anh)', 'lmf@o, lmfa0', 'BỊ CHẶN', 'Laughing my fucking ass off'],
    [113, 'mofo', 'TOXIC', 'Viết tắt (Anh)', 'mf, mfer, m0f0', 'BỊ CHẶN', 'Viết tắt motherfucker'],
    [114, 'fatass', 'TOXIC', 'Sỉ nhục (Anh)', 'fat@ss, fata$$, fat a$$', 'BỊ CHẶN', 'Body shaming'],
    [115, 'smartass', 'TOXIC', 'Sỉ nhục (Anh)', 'smart@ss, smarta$$', 'BỊ CHẶN', 'Xúc phạm'],

    // === Tiếng Anh - Phân biệt chủng tộc (Racial Slurs) ===
    [116, 'nigga', 'TOXIC', 'Phân biệt chủng tộc', 'n*gga, n1gga, nigg@, n!gga', 'BỊ CHẶN', 'Phân biệt chủng tộc'],
    [117, 'nigger', 'TOXIC', 'Phân biệt chủng tộc', 'n*gger, n1gger, nigg3r', 'BỊ CHẶN', 'Phân biệt chủng tộc nặng nhất'],
    [118, 'negro', 'TOXIC', 'Phân biệt chủng tộc', 'negr0, n3gro', 'BỊ CHẶN', 'Phân biệt chủng tộc'],
    [119, 'chink', 'TOXIC', 'Phân biệt chủng tộc', 'ch!nk, ch1nk', 'BỊ CHẶN', 'Xúc phạm người châu Á'],
    [120, 'gook', 'TOXIC', 'Phân biệt chủng tộc', 'g00k, g0ok', 'BỊ CHẶN', 'Xúc phạm người châu Á'],
    [121, 'spic', 'TOXIC', 'Phân biệt chủng tộc', 'sp!c, sp1c, spick', 'BỊ CHẶN', 'Xúc phạm người Latin'],
    [122, 'kike', 'TOXIC', 'Phân biệt chủng tộc', 'k!ke, k1ke', 'BỊ CHẶN', 'Xúc phạm người Do Thái'],
    [123, 'wetback', 'TOXIC', 'Phân biệt chủng tộc', 'w3tback, wetb@ck', 'BỊ CHẶN', 'Xúc phạm người nhập cư'],
    [124, 'cracker', 'TOXIC', 'Phân biệt chủng tộc', 'cr@cker, cr4cker', 'BỊ CHẶN', 'Phân biệt chủng tộc'],
    [125, 'honky', 'TOXIC', 'Phân biệt chủng tộc', 'h0nky, honkey', 'BỊ CHẶN', 'Phân biệt chủng tộc'],
    [126, 'gringo', 'TOXIC', 'Phân biệt chủng tộc', 'gr1ngo, gr!ngo', 'BỊ CHẶN', 'Xúc phạm dựa trên quốc tịch'],
    [127, 'beaner', 'TOXIC', 'Phân biệt chủng tộc', 'b3aner, be@ner', 'BỊ CHẶN', 'Xúc phạm người Latin'],
    [128, 'coon', 'TOXIC', 'Phân biệt chủng tộc', 'c00n, c0on', 'BỊ CHẶN', 'Phân biệt chủng tộc nặng'],

    // === Tiếng Anh - Kỳ thị LGBT / Khuyết tật ===
    [129, 'faggot', 'TOXIC', 'Kỳ thị LGBT', 'f@ggot, f4gg0t, fag', 'BỊ CHẶN', 'Xúc phạm người đồng tính'],
    [130, 'fag', 'TOXIC', 'Kỳ thị LGBT', 'f@g, f4g, phag', 'BỊ CHẶN', 'Xúc phạm người đồng tính'],
    [131, 'dyke', 'TOXIC', 'Kỳ thị LGBT', 'dyk3, d!ke', 'BỊ CHẶN', 'Xúc phạm người đồng tính nữ'],
    [132, 'tranny', 'TOXIC', 'Kỳ thị LGBT', 'tr@nny, tr4nny', 'BỊ CHẶN', 'Xúc phạm người chuyển giới'],
    [133, 'homo', 'TOXIC', 'Kỳ thị LGBT', 'h0mo, h@mo', 'BỊ CHẶN', 'Xúc phạm người đồng tính'],
    [134, 'retard', 'TOXIC', 'Kỳ thị khuyết tật', 'r3tard, ret@rd, retarded', 'BỊ CHẶN', 'Xúc phạm người khuyết tật'],
    [135, 'spaz', 'TOXIC', 'Kỳ thị khuyết tật', 'sp@z, sp4z, spastic', 'BỊ CHẶN', 'Xúc phạm người khuyết tật'],

    // === Tiếng Anh - Xúc phạm phụ nữ ===
    [136, 'whore', 'TOXIC', 'Xúc phạm phụ nữ (Anh)', 'wh0re, wh@re, h0e', 'BỊ CHẶN', 'Xúc phạm phụ nữ'],
    [137, 'slut', 'TOXIC', 'Xúc phạm phụ nữ (Anh)', 'sl*t, $lut, sl@t', 'BỊ CHẶN', 'Xúc phạm phụ nữ'],
    [138, 'cunt', 'TOXIC', 'Tục tĩu nặng (Anh)', 'c*nt, cünt, c@nt', 'BỊ CHẶN', 'Tục tĩu nặng nhất (Anh)'],
    [139, 'skank', 'TOXIC', 'Xúc phạm phụ nữ (Anh)', 'sk@nk, sk4nk', 'BỊ CHẶN', 'Xúc phạm phụ nữ'],
    [140, 'hoe', 'TOXIC', 'Xúc phạm phụ nữ (Anh)', 'h03, h@e, hoa (ngữ cảnh)', 'BỊ CHẶN', 'Xúc phạm phụ nữ'],
    [141, 'thot', 'TOXIC', 'Xúc phạm phụ nữ (Anh)', 'th0t, th@t, thotiana', 'BỊ CHẶN', 'That ho over there'],
    [142, 'harlot', 'TOXIC', 'Xúc phạm phụ nữ (Anh)', 'h@rlot, harl0t', 'BỊ CHẶN', 'Xúc phạm phụ nữ'],
    [143, 'trollop', 'TOXIC', 'Xúc phạm phụ nữ (Anh)', 'tr0llop, troll0p', 'BỊ CHẶN', 'Xúc phạm phụ nữ'],

    // === Tiếng Anh - Nội dung tình dục (Sexual) ===
    [144, 'porno', 'TOXIC', 'Nội dung 18+ (Anh)', 'p0rno, pr0no, pornography', 'BỊ CHẶN', 'Nội dung khiêu dâm'],
    [145, 'xxx', 'TOXIC', 'Nội dung 18+ (Anh)', 'XXX, x.x.x, xXx', 'BỊ CHẶN', 'Nội dung khiêu dâm'],
    [146, 'hentai', 'TOXIC', 'Nội dung 18+ (Anh)', 'h3ntai, hent@i', 'BỊ CHẶN', 'Anime khiêu dâm'],
    [147, 'nude', 'TOXIC', 'Nội dung 18+ (Anh)', 'nud3, nud€, nudes', 'BỊ CHẶN', 'Khỏa thân'],
    [148, 'naked', 'TOXIC', 'Nội dung 18+ (Anh)', 'nak3d, n@ked', 'BỊ CHẶN', 'Khỏa thân'],
    [149, 'boobs', 'TOXIC', 'Nội dung 18+ (Anh)', 'b00bs, bo0bs, bewbs', 'BỊ CHẶN', 'Bộ phận cơ thể'],
    [150, 'tits', 'TOXIC', 'Nội dung 18+ (Anh)', 't!ts, t1ts, titties, tiddies', 'BỊ CHẶN', 'Bộ phận cơ thể'],
    [151, 'dildo', 'TOXIC', 'Nội dung 18+ (Anh)', 'd!ldo, d1ldo', 'BỊ CHẶN', 'Đồ chơi tình dục'],
    [152, 'vibrator', 'TOXIC', 'Nội dung 18+ (Anh)', 'v!brator, vibrat0r', 'BỊ CHẶN', 'Đồ chơi tình dục'],
    [153, 'orgasm', 'TOXIC', 'Nội dung 18+ (Anh)', '0rgasm, org@sm', 'BỊ CHẶN', 'Nội dung tình dục'],
    [154, 'blowjob', 'TOXIC', 'Nội dung 18+ (Anh)', 'bl0wjob, bj, blow job', 'BỊ CHẶN', 'Hành vi tình dục'],
    [155, 'handjob', 'TOXIC', 'Nội dung 18+ (Anh)', 'h@ndjob, hand job, hj', 'BỊ CHẶN', 'Hành vi tình dục'],
    [156, 'cumshot', 'TOXIC', 'Nội dung 18+ (Anh)', 'cum$hot, cumsh0t, cum shot', 'BỊ CHẶN', 'Nội dung khiêu dâm'],
    [157, 'creampie', 'TOXIC', 'Nội dung 18+ (Anh)', 'cream pie, cr3ampie', 'BỊ CHẶN', 'Nội dung khiêu dâm'],
    [158, 'gangbang', 'TOXIC', 'Nội dung 18+ (Anh)', 'gang bang, g@ngbang', 'BỊ CHẶN', 'Nội dung khiêu dâm'],
    [159, 'threesome', 'TOXIC', 'Nội dung 18+ (Anh)', 'thr33some, 3some', 'BỊ CHẶN', 'Nội dung khiêu dâm'],
    [160, 'orgy', 'TOXIC', 'Nội dung 18+ (Anh)', '0rgy, org!e', 'BỊ CHẶN', 'Nội dung khiêu dâm'],
    [161, 'milf', 'TOXIC', 'Nội dung 18+ (Anh)', 'm!lf, m1lf, MILF', 'BỊ CHẶN', 'Nội dung khiêu dâm'],
    [162, 'anal', 'TOXIC', 'Nội dung 18+ (Anh)', '@nal, an@l, 4nal', 'BỊ CHẶN', 'Nội dung khiêu dâm'],
    [163, 'fellatio', 'TOXIC', 'Nội dung 18+ (Anh)', 'fell@tio, fellati0', 'BỊ CHẶN', 'Hành vi tình dục'],
    [164, 'cunnilingus', 'TOXIC', 'Nội dung 18+ (Anh)', 'cunn!lingus, cunnil1ngus', 'BỊ CHẶN', 'Hành vi tình dục'],
    [165, 'deepthroat', 'TOXIC', 'Nội dung 18+ (Anh)', 'deep throat, d33pthr0at', 'BỊ CHẶN', 'Nội dung khiêu dâm'],
    [166, 'bondage', 'TOXIC', 'Nội dung 18+ (Anh)', 'b0ndage, bond@ge, BDSM', 'BỊ CHẶN', 'Nội dung BDSM'],
    [167, 'dominatrix', 'TOXIC', 'Nội dung 18+ (Anh)', 'dom!natrix, d0minatrix', 'BỊ CHẶN', 'Nội dung BDSM'],
    [168, 'fetish', 'TOXIC', 'Nội dung 18+ (Anh)', 'f3tish, fet!sh', 'BỊ CHẶN', 'Nội dung 18+'],
    [169, 'erotic', 'TOXIC', 'Nội dung 18+ (Anh)', '3rotic, er0tic, erotica', 'BỊ CHẶN', 'Nội dung gợi dục'],
    [170, 'stripper', 'TOXIC', 'Nội dung 18+ (Anh)', 'str!pper, str1pper, strip club', 'BỊ CHẶN', 'Nội dung người lớn'],
    [171, 'escort', 'TOXIC', 'Nội dung 18+ (Anh)', '3scort, esc0rt, escort service', 'BỊ CHẶN', 'Dịch vụ tình dục'],
    [172, 'onlyfans', 'TOXIC', 'Nội dung 18+ (Anh)', '0nlyfans, only fans, 0nlyf@ns', 'BỊ CHẶN', 'Nền tảng nội dung 18+'],

    // === Tiếng Anh - Bạo lực / Đe dọa ===
    [173, 'kill yourself', 'TOXIC', 'Bạo lực / Đe dọa (Anh)', 'kys, k.y.s, kill urself', 'BỊ CHẶN', 'Cổ súy tự tử'],
    [174, 'go die', 'TOXIC', 'Bạo lực / Đe dọa (Anh)', 'g0 die, go d!e', 'BỊ CHẶN', 'Đe dọa bạo lực'],
    [175, 'neck yourself', 'TOXIC', 'Bạo lực / Đe dọa (Anh)', 'n3ck yourself, neck urself', 'BỊ CHẶN', 'Cổ súy tự tử'],
    [176, 'kys', 'TOXIC', 'Viết tắt đe dọa (Anh)', 'k.y.s, K.Y.S', 'BỊ CHẶN', 'Viết tắt "kill yourself"'],
    [177, 'rape', 'TOXIC', 'Bạo lực tình dục (Anh)', 'r@pe, r4pe, rap3', 'BỊ CHẶN', 'Bạo lực tình dục'],
    [178, 'molest', 'TOXIC', 'Bạo lực tình dục (Anh)', 'mol3st, m0lest', 'BỊ CHẶN', 'Lạm dụng tình dục'],
    [179, 'pedophile', 'TOXIC', 'Lạm dụng trẻ em (Anh)', 'p3do, ped0, pedo, paedo', 'BỊ CHẶN', 'Lạm dụng trẻ em - chặn tuyệt đối'],
    [180, 'incest', 'TOXIC', 'Nội dung cấm (Anh)', '!ncest, 1ncest, inc3st', 'BỊ CHẶN', 'Loạn luân'],

    // === Bổ sung VN - Tục tĩu / 18+ ===
    [181, 'chặt cu', 'TOXIC', 'Tục tĩu VN', 'chặt c.u, chat cu', 'BỊ CHẶN', 'Bạo lực tình dục'],
    [182, 'bao quy đầu', 'TOXIC', 'Tục tĩu VN', 'bao quy dau, bqd', 'BỊ CHẶN', 'Nội dung 18+'],
    [183, 'đầu khấc', 'TOXIC', 'Tục tĩu VN', 'dau khac, đầu khấk', 'BỊ CHẶN', 'Từ tục tĩu'],
    [184, 'địt mẹ', 'TOXIC', 'Chửi thề VN', 'dit me, đ.m, đm, d.m', 'BỊ CHẶN', 'Chửi thề nặng'],
    [185, 'cdmm', 'TOXIC', 'Viết tắt VN', 'c.d.m.m, c,d,m,m, cmm', 'BỊ CHẶN', 'Viết tắt chửi thề'],
    [186, 'xe lỗ nhị', 'TOXIC', 'Tục tĩu VN', 'xe lo nhi, xelonhi', 'BỊ CHẶN', 'Nội dung thô tục'],
    [187, 'xe lỗ đít', 'TOXIC', 'Tục tĩu VN', 'xe lo dit, xelodit', 'BỊ CHẶN', 'Nội dung thô tục'],
    [188, 'xe lông bướm', 'TOXIC', 'Tục tĩu VN', 'xe long buom, xelongbuom', 'BỊ CHẶN', 'Nội dung 18+ thô tục'],
    [189, 'bứt lông dái', 'TOXIC', 'Tục tĩu VN', 'but long dai, butlongdai', 'BỊ CHẶN', 'Nội dung 18+ thô tục'],
    [190, 'bứt lông cặc', 'TOXIC', 'Tục tĩu VN', 'but long cac, butlongcac', 'BỊ CHẶN', 'Nội dung 18+ thô tục'],
    [191, 'thông lỗ đít', 'TOXIC', 'Tục tĩu VN', 'thong lo dit, thonglodit', 'BỊ CHẶN', 'Nội dung 18+ thô tục'],
    [192, 'bú', 'TOXIC', 'Tục tĩu VN', 'b.u, bu~', 'BỊ CHẶN', 'Từ tục tĩu gợi dục'],
    [193, 'lít đỗ', 'TOXIC', 'Tục tĩu VN', 'lit do, lít đổ', 'BỊ CHẶN', 'Từ lóng tục tĩu'],
    [194, 'tù ngay', 'TOXIC', 'Tục tĩu VN', 'tu ngay, tùngay', 'BỊ CHẶN', 'Từ lóng tục tĩu'],
    [195, 'tà bỏ chay', 'TOXIC', 'Tục tĩu VN', 'ta bo chay, tabochay', 'BỊ CHẶN', 'Từ lóng tục tĩu'],
    [196, 'chặt con cặc', 'TOXIC', 'Tục tĩu VN', 'chat con cac, chặt con c.ặ.c', 'BỊ CHẶN', 'Bạo lực tình dục'],

    // === Bổ sung Anh - Viết tắt / Biến thể ===
    [197, 'f4ck', 'TOXIC', 'Biến thể chửi (Anh)', 'f4ck, f@ck, fvck', 'BỊ CHẶN', 'Biến thể leetspeak của fuck'],
    [198, 'bj', 'TOXIC', 'Viết tắt 18+ (Anh)', 'b.j, b j', 'BỊ CHẶN', 'Viết tắt blowjob'],
    [199, 'cu', 'TOXIC', 'Tục tĩu - Bộ phận cơ thể', 'cu, c.u, c u', 'BỊ CHẶN', 'Bộ phận sinh dục nam'],
    [200, 'kẹc', 'TOXIC', 'Tục tĩu - Bộ phận cơ thể', 'kec, k.ẹ.c, k ẹ c', 'BỊ CHẶN', 'Biến thể của cặc'],
    [201, 'cẹc', 'TOXIC', 'Tục tĩu - Bộ phận cơ thể', 'cec, c.ẹ.c, c ẹ c', 'BỊ CHẶN', 'Biến thể của cặc'],
    [202, 'ku', 'TOXIC', 'Tục tĩu - Bộ phận cơ thể', 'ku, k.u, k u', 'BỊ CHẶN', 'Biến thể của cu'],
];

$safeWords = [
    [1, 'Áo khoác mùa đông', 'SAFE', 'Quần áo', '', 'CHO QUA', 'Mô tả sản phẩm bình thường'],
    [2, 'Sách giáo khoa Toán lớp 10', 'SAFE', 'Sách vở', '', 'CHO QUA', 'Tên sản phẩm giáo dục'],
    [3, 'Quần áo trẻ em còn mới', 'SAFE', 'Quần áo', '', 'CHO QUA', 'Mô tả đồ quyên góp'],
    [4, 'Bàn học gỗ tự nhiên', 'SAFE', 'Nội thất', '', 'CHO QUA', 'Tên sản phẩm nội thất'],
    [5, 'Xe đạp cũ còn tốt', 'SAFE', 'Phương tiện', '', 'CHO QUA', 'Mô tả phương tiện'],
    [6, 'Tủ lạnh Samsung 200L', 'SAFE', 'Điện tử', '', 'CHO QUA', 'Tên sản phẩm điện tử'],
    [7, 'Đồ chơi giáo dục cho bé', 'SAFE', 'Đồ chơi', '', 'CHO QUA', 'Tên sản phẩm bình thường'],
    [8, 'Gạo, mì tôm, nước mắm', 'SAFE', 'Thực phẩm', '', 'CHO QUA', 'Danh sách thực phẩm'],
    [9, 'Giày thể thao Nike size 42', 'SAFE', 'Giày dép', '', 'CHO QUA', 'Tên sản phẩm thời trang'],
    [10, 'Laptop Dell cũ vẫn chạy tốt', 'SAFE', 'Điện tử', '', 'CHO QUA', 'Mô tả sản phẩm công nghệ'],
    [11, 'Bộ chén dĩa sứ 12 món', 'SAFE', 'Gia dụng', '', 'CHO QUA', 'Tên sản phẩm gia dụng'],
    [12, 'Nồi cơm điện Sunhouse', 'SAFE', 'Gia dụng', '', 'CHO QUA', 'Tên sản phẩm gia dụng'],
    [13, 'Quần jean nam còn mới 90%', 'SAFE', 'Quần áo', '', 'CHO QUA', 'Mô tả đồ quyên góp'],
    [14, 'Túi xách nữ da thật', 'SAFE', 'Phụ kiện', '', 'CHO QUA', 'Tên sản phẩm thời trang'],
    [15, 'Máy giặt Electrolux 8kg', 'SAFE', 'Điện tử', '', 'CHO QUA', 'Tên sản phẩm điện tử'],
];

$regexPatterns = [
    [1, 'c[^\\pL]*[aăặ][^\\pL]*[ck]', 'REGEX', 'Bắt biến thể: cặc', 'c.c, c@c, c$c, c#ặ#c, c%c, c&c', 'BỊ CHẶN', 'Bắt MỌI ký tự đặc biệt chèn giữa'],
    [2, 'l[^\\pL]*[oồôò][^\\pL]*n', 'REGEX', 'Bắt biến thể: lồn', 'l.n, l@n, l$ồ$n, l%n, l&n', 'BỊ CHẶN', 'Bắt MỌI ký tự đặc biệt chèn giữa'],
    [3, 'đ[^\\pL]*[iị][^\\pL]*t', 'REGEX', 'Bắt biến thể: địt', 'đ.t, đ@t, đ$ị$t, đ%t, đ&t', 'BỊ CHẶN', 'Bắt MỌI ký tự đặc biệt chèn giữa'],
    [4, 'd[^\\pL]*[iị][^\\pL]*t', 'REGEX', 'Bắt biến thể: dit (không dấu)', 'd.i.t, d@i@t, d$t', 'BỊ CHẶN', 'Bắt cả biến thể không dấu'],
    [5, 'b[^\\pL]*u[^\\pL]*[oồò][^\\pL]*i', 'REGEX', 'Bắt biến thể: buồi', 'b.u.ồ.i, b@u@ồ@i, b$u$ồ$i', 'BỊ CHẶN', 'Bắt MỌI ký tự đặc biệt chèn giữa'],
    [6, 'v[^\\pL]*[ck][^\\pL]*l', 'REGEX', 'Bắt biến thể: vcl/vkl', 'v.c.l, v@k@l, v$c$l, v%l', 'BỊ CHẶN', 'Bắt MỌI ký tự đặc biệt chèn giữa'],
    [7, 'd[^\\pL]*[uụ][^\\pL]*m[^\\pL]*[aá]', 'REGEX', 'Bắt biến thể: đụ má', 'd.u.m.a, d@u@m@a', 'BỊ CHẶN', 'Bắt cả biến thể không dấu'],
    [8, 'c[^\\pL]*[uứ][^\\pL]*t', 'REGEX', 'Bắt biến thể: cứt', 'c.ứ.t, c@ứ@t, c$t', 'BỊ CHẶN', 'Bắt MỌI ký tự đặc biệt chèn giữa'],
    [9, 'đ[^\\pL]*[eéê][^\\pL]*o', 'REGEX', 'Bắt biến thể: đéo', 'đ.é.o, đ@é@o, đ$é$o', 'BỊ CHẶN', 'Bắt MỌI ký tự đặc biệt chèn giữa'],
];

$nsfwImages = [
    // === KHỎA THÂN / NUDE ===
    [1, 'Khỏa thân toàn phần (Full Nudity)', 'NSFW', 'Hình ảnh lộ hoàn toàn bộ phận sinh dục, ngực trần', 'Nude photos, ảnh khiêu dâm, full frontal', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối, bất kể ngữ cảnh'],
    [2, 'Bán khỏa thân (Partial Nudity)', 'NSFW', 'Hình ảnh lộ phần lớn cơ thể, trang phục hở hang quá mức', 'Ảnh bikini quá hở, ảnh nội y, lingerie, topless', 'BỊ CHẶN', 'Cao', 'Chặn - không phù hợp nền tảng từ thiện'],
    [3, 'Lộ bộ phận nhạy cảm (Exposed Genitalia)', 'NSFW', 'Lộ vùng kín nam/nữ, dương vật, âm hộ, hậu môn', 'Dick pic, pussy pic, genital exposure, upskirt', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối - nội dung khiêu dâm'],
    [4, 'Lộ ngực (Exposed Breasts)', 'NSFW', 'Ngực trần nữ, lộ núm vú rõ ràng', 'Topless, boobs, tits, ảnh ngực trần, braless', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [5, 'Lộ mông (Exposed Buttocks)', 'NSFW', 'Hình ảnh lộ mông hoàn toàn, trang phục thong', 'Ass pic, butt naked, thong, g-string', 'BỊ CHẶN', 'Cao', 'Chặn - phản cảm'],

    // === TÌNH DỤC / SEXUAL ===
    [6, 'Nội dung tình dục (Sexual Content)', 'NSFW', 'Hành vi tình dục, giao hợp, oral sex rõ ràng', 'Porn, sex tape, intercourse, blowjob, handjob', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [7, 'Gợi dục rõ ràng (Explicit Suggestive)', 'NSFW', 'Tư thế gợi dục, mô phỏng hành vi tình dục', 'Ảnh tư thế 69, missionary, doggy style, lap dance', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [8, 'Gợi dục ngầm (Implicit Suggestive)', 'NSFW', 'Tư thế khêu gợi, góc chụp cố tình gợi cảm', 'Ảnh chụp góc nhạy cảm, tư thế đôi, wet t-shirt', 'BỊ CHẶN', 'Trung bình', 'Chặn - phòng ngừa nội dung không phù hợp'],
    [9, 'Đồ chơi tình dục (Sex Toys)', 'NSFW', 'Dương vật giả, máy rung, đồ chơi BDSM', 'Dildo, vibrator, butt plug, handcuffs BDSM, fleshlight', 'BỊ CHẶN', 'Rất cao', 'Chặn - sản phẩm 18+ không phù hợp từ thiện'],
    [10, 'BDSM / Fetish', 'NSFW', 'Trói buộc, roi, bạo dâm, khẩu trang bondage', 'Bondage, rope play, ball gag, leather harness, latex', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối - nội dung bạo dâm'],
    [11, 'Hentai / Anime 18+', 'NSFW', 'Hoạt hình, manga có nội dung tình dục, khiêu dâm', 'Hentai, ecchi, rule34, anime porn, doujinshi 18+', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối - dù là vẽ vẫn là nội dung 18+'],
    [12, 'Nội dung người lớn kiểu meme (Sexual Meme)', 'NSFW', 'Meme có nội dung tình dục, bộ phận cơ thể, gợi dục', 'NSFW meme, sexual joke images, ahegao face', 'BỊ CHẶN', 'Cao', 'Chặn - dù là meme vẫn phản cảm'],

    // === BẠO LỰC / VIOLENCE ===
    [13, 'Bạo lực đẫm máu (Graphic Violence)', 'NSFW', 'Hình ảnh máu me, thương tích nặng, cảnh giết chóc', 'Vết thương hở, cảnh chiến tranh, torture', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [14, 'Gore / Kinh dị (Gore / Horror)', 'NSFW', 'Hình ảnh ghê rợn, xác chết, bộ phận cơ thể bị tách rời', 'Dismemberment, autopsy, mutilation, decapitation', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [15, 'Tra tấn / Hành hạ (Torture)', 'NSFW', 'Cảnh tra tấn, ngược đãi con người hoặc động vật', 'Waterboarding, electrocution, animal cruelty, abuse', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối - vi phạm nhân quyền'],
    [16, 'Ngược đãi động vật (Animal Cruelty)', 'NSFW', 'Bạo hành, giết hại động vật dã man', 'Đánh đập thú cưng, chọi chó, giết mổ dã man', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [17, 'Tai nạn thảm khốc (Graphic Accidents)', 'NSFW', 'Cảnh tai nạn giao thông, lao động kinh hoàng', 'Ảnh tai nạn xe, rơi từ cao, cháy nổ có nạn nhân', 'BỊ CHẶN', 'Rất cao', 'Chặn - gây ám ảnh tâm lý'],

    // === VŨ KHÍ / WEAPONS ===
    [18, 'Vũ khí đe dọa (Threatening Weapons)', 'NSFW', 'Hình ảnh vũ khí trong ngữ cảnh đe dọa, bạo lực', 'Người cầm súng/dao chĩa vào camera, brandishing', 'BỊ CHẶN', 'Cao', 'Chặn - vũ khí trong bối cảnh bạo lực'],
    [19, 'Vũ khí tấn công (Assault Weapons)', 'NSFW', 'Súng trường, súng máy, bom, lựu đạn, chất nổ', 'AR-15, AK-47, pipe bomb, IED, grenade', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối - vũ khí quân sự'],
    [20, 'Dao / vũ khí sắc nhọn đe dọa', 'NSFW', 'Dao găm, kiếm, mã tấu trong bối cảnh đe dọa', 'Cầm dao dọa giết, machete, switchblade', 'BỊ CHẶN', 'Cao', 'Chặn - bạo lực'],

    // === MA TÚY / DRUGS ===
    [21, 'Ma túy / Chất cấm (Drugs)', 'NSFW', 'Hình ảnh ma túy, dụng cụ sử dụng ma túy', 'Cocaine, heroin, meth, cần sa, kim tiêm, ống hít', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối - vi phạm pháp luật'],
    [22, 'Sử dụng ma túy (Drug Use)', 'NSFW', 'Cảnh hít, chích, hút ma túy', 'Snorting lines, injecting, smoking crack/meth pipe', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [23, 'Thuốc lắc / Chất kích thích (Party Drugs)', 'NSFW', 'Viên nén ecstasy, LSD, nấm ảo giác', 'Ecstasy pills, LSD tabs, magic mushrooms, molly', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],

    // === THÙ HẬN / HATE ===
    [24, 'Nội dung thù hận (Hate Content)', 'NSFW', 'Biểu tượng phân biệt chủng tộc, phát xít, kỳ thị', 'Swastika, KKK, Confederate flag, biểu ngữ kỳ thị', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối - vi phạm nhân quyền'],
    [25, 'Phân biệt chủng tộc (Racist Imagery)', 'NSFW', 'Hình ảnh chế nhạo, xúc phạm dựa trên sắc tộc', 'Blackface, racist caricature, racial slur imagery', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [26, 'Kỳ thị giới tính / LGBT (Homophobia)', 'NSFW', 'Hình ảnh chế nhạo, xúc phạm cộng đồng LGBT', 'Anti-gay propaganda, transphobic memes', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [27, 'Khủng bố / Cực đoan (Terrorism)', 'NSFW', 'Cờ ISIS, tuyên truyền cực đoan, hình ảnh khủng bố', 'ISIS flag, jihad propaganda, extremist symbols', 'BỊ CHẶN', 'Tối đa', 'Chặn tuyệt đối + báo cáo'],

    // === TRẺ EM / CHILDREN ===
    [28, 'Lạm dụng trẻ em (Child Abuse/CSAM)', 'NSFW', 'BẤT KỲ nội dung liên quan đến lạm dụng tình dục trẻ em', 'Child exploitation, CSAM, pedophilia imagery', 'BỊ CHẶN', 'Tối đa', 'Chặn tuyệt đối + báo cáo cơ quan chức năng NGAY'],
    [29, 'Trẻ em bối cảnh không phù hợp', 'NSFW', 'Trẻ em xuất hiện cùng nội dung người lớn, gợi dục', 'Minor in suggestive context, child in adult setting', 'BỊ CHẶN', 'Tối đa', 'Chặn tuyệt đối + báo cáo ngay'],

    // === TỰ HẠI / SELF-HARM ===
    [30, 'Tự gây hại (Self-Harm)', 'NSFW', 'Hình ảnh tự gây thương tích, cổ súy tự tử', 'Vết cắt tự gây, self-cutting, razor blade on wrist', 'BỊ CHẶN', 'Rất cao', 'Chặn + hiển thị hotline hỗ trợ'],
    [31, 'Cổ súy tự tử (Suicide Promotion)', 'NSFW', 'Hướng dẫn, khuyến khích tự tử', 'Noose, bridge jumping, suicide methods', 'BỊ CHẶN', 'Rất cao', 'Chặn + hiển thị hotline hỗ trợ tâm lý'],
    [32, 'Rối loạn ăn uống (Eating Disorder)', 'NSFW', 'Cổ súy bỏ đói, ảnh pro-ana, thinspo cực đoan', 'Pro-anorexia, thinspo, extreme weight loss glorification', 'BỊ CHẶN', 'Cao', 'Chặn - nguy hiểm sức khỏe tâm thần'],

    // === RỰU BIA / ALCOHOL ===
    [33, 'Rượu bia / Thuốc lá (Alcohol/Tobacco)', 'NSFW', 'Quảng cáo rượu, thuốc lá, vape rõ ràng', 'Ảnh chai rượu vodka, điếu thuốc, vape/e-cig ads', 'BỊ CHẶN', 'Trung bình', 'Chặn - không phù hợp nền tảng từ thiện'],

    // === NỘI DUNG 18+ TIẾNG ANH (ENGLISH NSFW KEYWORDS) ===
    [34, 'Porn / Pornography', 'NSFW', 'Nội dung khiêu dâm, video/ảnh sex rõ ràng', 'Porn, porno, pornhub, xvideos, xnxx, xxx', 'BỊ CHẶN', 'Rất cao', 'Chặn mọi từ khóa liên quan porn'],
    [35, 'Nude / Naked', 'NSFW', 'Ảnh khỏa thân, nude selfie, naked pic', 'Nude, nudes, naked, bare, unclothed, stripped', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [36, 'Sexy / Hot / Seductive', 'NSFW', 'Ảnh gợi cảm, khêu gợi quá mức', 'Sexy, hot girl, seductive, provocative, sultry', 'BỊ CHẶN', 'Cao', 'Chặn trên nền tảng từ thiện'],
    [37, 'Boobs / Tits / Breasts', 'NSFW', 'Từ khóa chỉ ngực nữ tục tĩu', 'Boobs, tits, titties, knockers, jugs, rack', 'BỊ CHẶN', 'Rất cao', 'Chặn - từ ngữ 18+'],
    [38, 'Ass / Butt / Booty', 'NSFW', 'Từ khóa chỉ mông tục tĩu', 'Ass, butt, booty, thicc, bubble butt, twerk', 'BỊ CHẶN', 'Rất cao', 'Chặn - từ ngữ 18+'],
    [39, 'Dick / Cock / Penis', 'NSFW', 'Từ khóa chỉ dương vật', 'Dick, cock, penis, dong, shaft, dick pic', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [40, 'Pussy / Vagina / Cunt', 'NSFW', 'Từ khóa chỉ âm hộ tục tĩu', 'Pussy, vagina, cunt, cooch, snatch, twat', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [41, 'Blowjob / Oral Sex', 'NSFW', 'Hành vi oral sex', 'Blowjob, BJ, head, deepthroat, fellatio, cunnilingus', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [42, 'Anal / Penetration', 'NSFW', 'Hành vi quan hệ hậu môn, xâm nhập', 'Anal, penetration, double penetration, DP, gaping', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [43, 'Cumshot / Creampie / Facial', 'NSFW', 'Ảnh/video xuất tinh, nội dung explicit', 'Cumshot, creampie, facial, cum, money shot', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [44, 'Gangbang / Orgy / Threesome', 'NSFW', 'Quan hệ tình dục nhóm', 'Gangbang, orgy, threesome, foursome, group sex', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [45, 'MILF / Stepmom / Incest', 'NSFW', 'Nội dung 18+ liên quan gia đình, loạn luân', 'MILF, stepmom, stepdad, incest, family taboo', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối'],
    [46, 'OnlyFans / Cam Girl / Escort', 'NSFW', 'Quảng cáo dịch vụ tình dục, webcam sex', 'OnlyFans link, cam girl, escort service, sugar daddy', 'BỊ CHẶN', 'Rất cao', 'Chặn - quảng cáo dịch vụ 18+'],
    [47, 'Upskirt / Voyeur / Creepshot', 'NSFW', 'Chụp lén, quay lén, xâm phạm quyền riêng tư', 'Upskirt, voyeur, hidden cam, spy cam, creepshot', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối - vi phạm pháp luật'],
    [48, 'Revenge Porn / Non-consent', 'NSFW', 'Ảnh/video tình dục phát tán không đồng ý', 'Revenge porn, leaked nudes, stolen intimate photos', 'BỊ CHẶN', 'Tối đa', 'Chặn tuyệt đối - vi phạm pháp luật nghiêm trọng'],

    // === KÝ HIỆU / SYMBOL BYPASS ===
    [49, 'Ký hiệu 18+ (18+ Symbols)', 'NSFW', 'Dùng ký hiệu để ngụ ý nội dung 18+', 'Biểu tượng 🍆🍑🔞💦, emoji gợi dục', 'BỊ CHẶN', 'Cao', 'Chặn emoji/ký hiệu gợi dục rõ ràng'],
    [50, 'QR Code / Link 18+', 'NSFW', 'QR code hoặc link dẫn đến trang web 18+', 'QR pornhub, link xvideos, URL trang sex', 'BỊ CHẶN', 'Rất cao', 'Chặn - dẫn người dùng đến nội dung cấm'],

    // === NỘI DUNG PHẢN CẢM KHÁC ===
    [51, 'Phân / Nước tiểu (Scat / Watersports)', 'NSFW', 'Ảnh liên quan đến chất thải cơ thể, fetish', 'Scat, golden shower, watersports, copro', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối - phản cảm cực độ'],
    [52, 'Zoophilia / Beastility', 'NSFW', 'Nội dung tình dục với động vật', 'Bestiality, zoophilia, animal sex', 'BỊ CHẶN', 'Tối đa', 'Chặn tuyệt đối - vi phạm pháp luật'],
    [53, 'Necrophilia', 'NSFW', 'Nội dung tình dục với xác chết', 'Necrophilia, necro, sex with dead', 'BỊ CHẶN', 'Tối đa', 'Chặn tuyệt đối - vi phạm pháp luật'],
    [54, 'Deepfake 18+', 'NSFW', 'Ảnh/video deepfake khiêu dâm, ghép mặt vào nội dung sex', 'Deepfake porn, face swap porn, AI-generated NSFW', 'BỊ CHẶN', 'Tối đa', 'Chặn tuyệt đối - vi phạm pháp luật'],
    [55, 'Sticker / GIF 18+', 'NSFW', 'Sticker, GIF động có nội dung tình dục, khiêu dâm', 'NSFW sticker, porn GIF, hentai GIF, sex animation', 'BỊ CHẶN', 'Rất cao', 'Chặn tuyệt đối - dù là sticker/GIF vẫn cấm'],
];

$safeImages = [
    [1, 'Quần áo sạch sẽ', 'SAFE', 'Ảnh quần áo đã gấp gọn, treo trên móc', 'Áo khoác, quần jean, váy đầm', 'CHO QUA', 'Thấp', 'Nội dung quyên góp phổ biến nhất'],
    [2, 'Đồ gia dụng', 'SAFE', 'Ảnh đồ dùng nhà bếp, nội thất, thiết bị', 'Nồi cơm, bàn ghế, tủ lạnh', 'CHO QUA', 'Thấp', 'Kiểm tra đồ có còn dùng được không'],
    [3, 'Sách vở', 'SAFE', 'Ảnh sách giáo khoa, truyện, tài liệu học tập', 'Sách Toán lớp 10, truyện cổ tích', 'CHO QUA', 'Thấp', 'Nội dung giáo dục - luôn an toàn'],
    [4, 'Đồ chơi trẻ em', 'SAFE', 'Ảnh đồ chơi giáo dục, búp bê, xe mô hình', 'Lego, gấu bông, xe đua', 'CHO QUA', 'Thấp', 'Kiểm tra không có vũ khí đồ chơi giống thật'],
    [5, 'Thiết bị điện tử', 'SAFE', 'Ảnh laptop, điện thoại, máy tính bảng', 'MacBook cũ, iPhone, Samsung Tab', 'CHO QUA', 'Thấp', 'Đồ điện tử quyên góp'],
    [6, 'Thực phẩm đóng gói', 'SAFE', 'Ảnh gạo, mì, đồ hộp chưa mở', 'Thùng mì tôm, bao gạo 5kg', 'CHO QUA', 'Thấp', 'Kiểm tra hạn sử dụng trong mô tả'],
    [7, 'Xe đạp / Phương tiện', 'SAFE', 'Ảnh xe đạp, xe lăn, phương tiện đi lại', 'Xe đạp cũ, xe lăn y tế', 'CHO QUA', 'Thấp', 'Nội dung an toàn'],
    [8, 'Dụng cụ y tế', 'SAFE', 'Ảnh nạng, gậy chống, máy đo huyết áp', 'Nạng gỗ, máy đo đường huyết', 'CHO QUA', 'Thấp', 'Thiết bị hỗ trợ y tế - an toàn'],
    [9, 'Chăn màn / Nệm', 'SAFE', 'Ảnh chăn, gối, nệm còn sạch sẽ', 'Chăn bông, gối ôm, nệm foam', 'CHO QUA', 'Thấp', 'Nội dung bình thường'],
    [10, 'Giày dép', 'SAFE', 'Ảnh giày, dép, sandal các loại', 'Giày thể thao, dép xỏ ngón', 'CHO QUA', 'Thấp', 'Nội dung bình thường'],
];

// ============================================================
// XLSX BUILDER
// ============================================================

class SimpleXlsx {
    private $sheets = [];
    private $sharedStrings = [];
    private $stringIndex = [];

    public function addSheet($name, $rows, $colWidths = [], $merges = []) {
        $this->sheets[] = compact('name', 'rows', 'colWidths', 'merges');
    }

    private function addString($str) {
        $str = (string)$str;
        if (!isset($this->stringIndex[$str])) {
            $this->stringIndex[$str] = count($this->sharedStrings);
            $this->sharedStrings[] = $str;
        }
        return $this->stringIndex[$str];
    }

    private function colLetter($col) {
        $l = '';
        while ($col >= 0) {
            $l = chr(65 + ($col % 26)) . $l;
            $col = intval($col / 26) - 1;
        }
        return $l;
    }

    public function save($path) {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Cannot create: $path");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->sheetXml($sheet));
        }

        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStringsXml());
        $zip->close();
    }

    private function contentTypes() {
        $s = '';
        foreach ($this->sheets as $i => $_) {
            $s .= '<Override PartName="/xl/worksheets/sheet' . ($i+1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
. $s . '</Types>';
    }

    private function rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function workbookRels() {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
<Relationship Id="rIdStrings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        foreach ($this->sheets as $i => $_) {
            $x .= '<Relationship Id="rIdSheet'.($i+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i+1).'.xml"/>';
        }
        return $x . '</Relationships>';
    }

    private function workbook() {
        $s = '';
        foreach ($this->sheets as $i => $sh) {
            $s .= '<sheet name="' . htmlspecialchars($sh['name'], ENT_XML1, 'UTF-8') . '" sheetId="'.($i+1).'" r:id="rIdSheet'.($i+1).'"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>' . $s . '</sheets></workbook>';
    }

    private function stylesXml() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="6">
  <font><sz val="11"/><name val="Calibri"/></font>
  <font><b/><sz val="12"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><b/><color rgb="FFCC0000"/><sz val="11"/><name val="Calibri"/></font>
  <font><color rgb="FF006600"/><sz val="11"/><name val="Calibri"/></font>
  <font><sz val="10"/><color rgb="FF333333"/><name val="Calibri"/></font>
</fonts>
<fills count="8">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF2C3E50"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFE74C3C"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF27AE60"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFFE0E0"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFE0FFE0"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF8E44AD"/></patternFill></fill>
</fills>
<borders count="2">
  <border><left/><right/><top/><bottom/><diagonal/></border>
  <border>
    <left style="thin"><color auto="1"/></left>
    <right style="thin"><color auto="1"/></right>
    <top style="thin"><color auto="1"/></top>
    <bottom style="thin"><color auto="1"/></bottom>
    <diagonal/>
  </border>
</borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="8">
  <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
  <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
  <xf numFmtId="0" fontId="2" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
  <xf numFmtId="0" fontId="2" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="3" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment wrapText="1"/></xf>
  <xf numFmtId="0" fontId="4" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment wrapText="1"/></xf>
  <xf numFmtId="0" fontId="5" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>
</cellXfs>
</styleSheet>';
    }

    private function sheetXml($sheet) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        if (!empty($sheet['colWidths'])) {
            $xml .= '<cols>';
            foreach ($sheet['colWidths'] as $i => $w) {
                $xml .= '<col min="'.($i+1).'" max="'.($i+1).'" width="'.$w.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($sheet['rows'] as $ri => $row) {
            $rn = $ri + 1;
            $ht = isset($row['_height']) ? ' ht="'.$row['_height'].'" customHeight="1"' : '';
            $xml .= '<row r="'.$rn.'"'.$ht.'>';
            foreach ($row['cells'] as $ci => $cell) {
                $ref = $this->colLetter($ci) . $rn;
                $s = isset($cell['style']) ? $cell['style'] : 0;
                $v = isset($cell['value']) ? $cell['value'] : '';
                if (is_int($v)) {
                    $xml .= '<c r="'.$ref.'" s="'.$s.'"><v>'.$v.'</v></c>';
                } else {
                    $si = $this->addString($v);
                    $xml .= '<c r="'.$ref.'" t="s" s="'.$s.'"><v>'.$si.'</v></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if (!empty($sheet['merges'])) {
            $xml .= '<mergeCells count="'.count($sheet['merges']).'">';
            foreach ($sheet['merges'] as $m) $xml .= '<mergeCell ref="'.$m.'"/>';
            $xml .= '</mergeCells>';
        }

        return $xml . '</worksheet>';
    }

    private function sharedStringsXml() {
        $c = count($this->sharedStrings);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$c.'" uniqueCount="'.$c.'">';
        foreach ($this->sharedStrings as $s) {
            $xml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
        }
        return $xml . '</sst>';
    }
}

// ============================================================
// BUILD SHEET 1: TỪ NGỮ
// ============================================================
$xlsx = new SimpleXlsx();
$rows = [];
$merges = [];

// Helper: make row of cells with same style
function makeRow($values, $style) {
    $cells = [];
    foreach ($values as $v) $cells[] = ['value' => $v, 'style' => $style];
    return ['cells' => $cells];
}

function makeSectionRow($text, $style, $colCount) {
    $cells = [['value' => $text, 'style' => $style]];
    for ($i = 1; $i < $colCount; $i++) $cells[] = ['value' => '', 'style' => $style];
    return ['cells' => $cells, '_height' => 25];
}

// Header
$rows[] = makeRow(['STT', 'Từ/Cụm từ', 'Phân loại', 'Danh mục', 'Biến thể', 'Kết quả mong đợi', 'Ghi chú'], 1);

// Section: TOXIC
$r = count($rows) + 1;
$rows[] = makeSectionRow('DANH SACH TU CAM (TOXIC) - Ket qua mong doi: BI CHAN', 2, 7);
$merges[] = "A{$r}:G{$r}";

foreach ($toxicWords as $w) {
    $rows[] = makeRow([$w[0], $w[1], $w[2], $w[3], $w[4], $w[5], $w[6]], 5);
}

// Empty
$rows[] = makeRow(['', '', '', '', '', '', ''], 0);

// Section: SAFE
$r = count($rows) + 1;
$rows[] = makeSectionRow('VAN BAN AN TOAN (SAFE) - Ket qua mong doi: CHO QUA', 3, 7);
$merges[] = "A{$r}:G{$r}";

foreach ($safeWords as $w) {
    $rows[] = makeRow([$w[0], $w[1], $w[2], $w[3], $w[4], $w[5], $w[6]], 6);
}

// Empty
$rows[] = makeRow(['', '', '', '', '', '', ''], 0);

// Section: REGEX
$r = count($rows) + 1;
$rows[] = makeSectionRow('REGEX PATTERNS - Bat bien the kho (chen ky tu dac biet giua chu cai)', 4, 7);
$merges[] = "A{$r}:G{$r}";

$rows[] = makeRow(['STT', 'Regex Pattern', 'Phan loai', 'Mo ta', 'Vi du bat duoc', 'Ket qua', ''], 1);

foreach ($regexPatterns as $rp) {
    $rows[] = makeRow([$rp[0], $rp[1], $rp[2], $rp[3], $rp[4], $rp[5], $rp[6]], 0);
}

$xlsx->addSheet('Tu ngu', $rows, [6, 25, 15, 25, 35, 18, 40], $merges);

// ============================================================
// BUILD SHEET 2: ẢNH
// ============================================================
$rows2 = [];
$merges2 = [];

$rows2[] = makeRow(['STT', 'Loai noi dung', 'Phan loai', 'Mo ta chi tiet', 'Vi du cu the', 'Ket qua mong doi', 'Muc do', 'Ghi chu cho AI'], 1);

// Section: NSFW
$r = count($rows2) + 1;
$rows2[] = makeSectionRow('NOI DUNG BI CHAN (NSFW) - Anh vi pham quy dinh', 2, 8);
$merges2[] = "A{$r}:H{$r}";

foreach ($nsfwImages as $img) {
    $rows2[] = makeRow([$img[0], $img[1], $img[2], $img[3], $img[4], $img[5], $img[6], $img[7]], 5);
}

$rows2[] = makeRow(['', '', '', '', '', '', '', ''], 0);

// Section: SAFE
$r = count($rows2) + 1;
$rows2[] = makeSectionRow('NOI DUNG AN TOAN (SAFE) - Anh quyen gop hop le', 3, 8);
$merges2[] = "A{$r}:H{$r}";

foreach ($safeImages as $img) {
    $rows2[] = makeRow([$img[0], $img[1], $img[2], $img[3], $img[4], $img[5], $img[6], $img[7]], 6);
}

$rows2[] = makeRow(['', '', '', '', '', '', '', ''], 0);
$rows2[] = makeRow(['', '', '', '', '', '', '', ''], 0);

// Section: Prompt ảnh
$r = count($rows2) + 1;
$rows2[] = makeSectionRow('PROMPT KIEM DUYET ANH CHO GEMINI VISION', 4, 8);
$merges2[] = "A{$r}:H{$r}";

$imgPrompt = "Ban la he thong kiem duyet hinh anh chuyen nghiep cho nen tang tu thien Goodwill Vietnam.\n"
    . "Phan tich hinh anh va xac dinh co noi dung KHONG PHU HOP khong.\n\n"
    . "Noi dung KHONG PHU HOP bao gom:\n"
    . "- Khoa than (toan phan/ban khoa than), lo bo phan sinh duc, nguc tran, mong\n"
    . "- Noi dung tinh duc, giao hop, oral sex, anal, BDSM, fetish\n"
    . "- Do choi tinh duc (dildo, vibrator, butt plug)\n"
    . "- Hentai, anime 18+, deepfake porn, NSFW meme/sticker/GIF\n"
    . "- Goi duc ro rang hoac ngam (tu the kheu goi, wet t-shirt, upskirt)\n"
    . "- Porn, nude selfie, OnlyFans, cam girl, escort ads\n"
    . "- Revenge porn, chup len (voyeur, creepshot, hidden cam)\n"
    . "- Bao luc dam mau, gore, tra tan, dismemberment, decapitation\n"
    . "- Nguoc dai dong vat, tai nan tham khoc co nan nhan\n"
    . "- Vu khi de doa, sung chia vao camera, dao trong boi canh bao luc\n"
    . "- Ma tuy (cocaine, heroin, meth, can sa), dung cu su dung, thuoc lac\n"
    . "- Hinh anh phan cam: scat, zoophilia, necrophilia\n"
    . "- Noi dung thu han, phan biet chung toc (swastika, KKK, blackface)\n"
    . "- Khung bo, cuc doan (ISIS, propaganda cuc doan)\n"
    . "- Lam dung tre em (CSAM) - BAT KY noi dung nao lien quan tre em trong boi canh 18+\n"
    . "- Tu gay hai, co suy tu tu, roi loan an uong (pro-ana, thinspo)\n"
    . "- Ruou bia, thuoc la, vape (quang cao ro rang)\n"
    . "- Emoji/ky hieu goi duc, QR code/link dan den trang 18+\n\n"
    . "Tra loi FORMAT:\n"
    . "Dong 1: SAFE hoac NSFW\n"
    . "Dong 2: Ly do ngan gon (1 cau tieng Viet)";

$startR = count($rows2) + 1;
for ($i = 0; $i < 5; $i++) {
    $v = ($i === 0) ? $imgPrompt : '';
    $rows2[] = makeRow([$v, '', '', '', '', '', '', ''], 7);
}
$endR = count($rows2);
$merges2[] = "A{$startR}:H{$endR}";

$rows2[] = makeRow(['', '', '', '', '', '', '', ''], 0);

// Section: Prompt text
$r = count($rows2) + 1;
$rows2[] = makeSectionRow('PROMPT KIEM DUYET VAN BAN CHO GEMINI', 4, 8);
$merges2[] = "A{$r}:H{$r}";

$textPrompt = "Ban la he thong kiem duyet noi dung tieng Viet chuyen nghiep.\n\n"
    . "Kiem tra van ban co chua noi dung VI PHAM:\n"
    . "- Tu tuc tiu, chui the, tho thien (d.m, d*t, cc, vcl, dm, dkm, clm...)\n"
    . "- Bien the ne filter: viet tat, chen dau cham/sao/space\n"
    . "- Si nhuc, xuc pham, phan biet\n"
    . "- Noi dung 18+, khieu dam, goi duc\n"
    . "- Loi le de doa, kich dong bao luc\n\n"
    . "Tra loi FORMAT:\n"
    . "Dong 1: SAFE hoac TOXIC\n"
    . "Dong 2: Neu TOXIC, ghi ly do ngan gon bang tieng Viet";

$startR = count($rows2) + 1;
for ($i = 0; $i < 5; $i++) {
    $v = ($i === 0) ? $textPrompt : '';
    $rows2[] = makeRow([$v, '', '', '', '', '', '', ''], 7);
}
$endR = count($rows2);
$merges2[] = "A{$startR}:H{$endR}";

$xlsx->addSheet('Anh', $rows2, [6, 28, 12, 40, 35, 18, 18, 45], $merges2);

// ============================================================
// SAVE
// ============================================================
$xlsx->save($outputPath);

echo "=== TrainingAI.xlsx ===" . PHP_EOL;
echo "Da tao thanh cong!" . PHP_EOL;
echo PHP_EOL;
echo "Sheet 1 - Tu ngu:" . PHP_EOL;
echo "  - " . count($toxicWords) . " tu cam (TOXIC)" . PHP_EOL;
echo "  - " . count($safeWords) . " mau an toan (SAFE)" . PHP_EOL;
echo "  - " . count($regexPatterns) . " regex patterns" . PHP_EOL;
echo PHP_EOL;
echo "Sheet 2 - Anh:" . PHP_EOL;
echo "  - " . count($nsfwImages) . " loai NSFW" . PHP_EOL;
echo "  - " . count($safeImages) . " loai SAFE" . PHP_EOL;
echo "  - 2 prompts AI (Image + Text)" . PHP_EOL;
echo PHP_EOL;
echo "File: " . realpath($outputPath) . PHP_EOL;

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <!-- import CSS -->
    <link rel="stylesheet" href="https://unpkg.com/element-ui/lib/theme-chalk/index.css">
</head>
<body>
<div id="app">
    <el-container>
        <el-header>上传车辆细节图片</el-header>
{{--        <el-header>注意（命名格式：<el-tag type="warning">车型_颜色.格式后缀</el-tag> 示例：<el-tag type="warning">Mana 850_黑色_logo.jpeg 或 Mana 850_黑色_细节.jpeg</el-tag>）（支持图片格式：<el-tag type="warning">.jpeg 或 .jpg 或 .png</el-tag>）</el-header>--}}
        <el-main>
            <el-select
                    v-model="value"
                    multiple
                    filterable
                    remote
                    reserve-keyword
                    placeholder="请输入车型关键词1"
                    :remote-method="remoteMethod"
                    :loading="loading">
                <el-option
                        v-for="item in options"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value">
                </el-option>
            </el-select>
            <el-select
                    v-model="image_type"
                    placeholder="请选择图片类型">
                <el-option key="overview" label="整车图片" value="overview"></el-option>
                <el-option key="detail" label="局部细节图片" value="details"></el-option>
                <el-option key="official" label="官方图片" value="official"></el-option>
            </el-select>
            <el-input style="width: 10%;" v-model="color" placeholder="请输入车辆颜色"></el-input>
            <el-upload
                    action="/moto/upload/detail_img"
                    :data="selected_val"
                    :auto-upload="false"
                    :multiple="multiple"
                    name="file"
                    list-type="picture"
                    header="multipart/form-data"
                    accept="image/jpeg,image/png,image/gif"
                    class="upload-demo"
                    ref="upload"
                    :on-preview="handlePreview"
                    :on-remove="handleRemove"
                    :on-success="returnFunc"
                    :file-list="fileList">
                <el-button slot="trigger" size="small" type="primary">选取文件</el-button>
                <el-button style="margin-left: 10px;" size="small" type="success" @click="submitUpload">上传到服务器</el-button>
            </el-upload>
        </el-main>
    </el-container>
</div>
</body>
<script src="https://cdn.jsdelivr.net/npm/vue@2.6.13"></script>
<!-- import JavaScript -->
<script src="https://unpkg.com/element-ui/lib/index.js"></script>

<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script>
    let list = {!! json_encode($list, 256) !!}
    new Vue({
        el: '#app',
        data() {
            return {
                str: '123',
                fileList: [],
                multiple: true,
                switch_value: false,
                list: list,
                options: [],
                value: [],
                image_type: '',
                color: '',
                loading: false,
                selected_val: {
                    id: '',
                    image_type: '',
                    color: ''
                },
                full_loading: false
            }
        },
        methods: {
            remoteMethod(query) {
                if (query !== '') {
                    this.loading = true;
                    setTimeout(() => {
                        this.loading = false;
                        this.options = this.list.filter(item => {
                            return item.label.toLowerCase()
                                .indexOf(query.toLowerCase()) > -1;
                        });
                    }, 200);
                } else {
                    this.options = [];
                }
            },
            submitUpload() {
                this.selected_val.id = this.value[0]
                this.selected_val.image_type = this.image_type
                this.selected_val.color = this.color
                this.$refs.upload.submit()

            },
            handleRemove(file, fileList) {
                console.log(file, fileList);
            },
            handlePreview(file) {
                console.log(file);
            },
            returnFunc(res) {
                if (res.code === 200) {
                    this.$message.success(res.name + ' ' + res.message)
                } else {
                    this.$message.error(res.name + ' ' + res.message)
                }
            },
        }
    })
</script>
</html>

<style>
    .el-header, .el-footer {
        background-color: #B3C0D1;
        color: #333;
        text-align: center;
        line-height: 60px;
    }

    .el-aside {
        background-color: #D3DCE6;
        color: #333;
        text-align: center;
        line-height: 200px;
    }

    .el-main {
        background-color: #E9EEF3;
        color: #333;
        text-align: center;
        line-height: 160px;
    }

    body > .el-container {
        margin-bottom: 40px;
    }

    .el-container:nth-child(5) .el-aside,
    .el-container:nth-child(6) .el-aside {
        line-height: 260px;
    }

    .el-container:nth-child(7) .el-aside {
        line-height: 320px;
    }
</style>
